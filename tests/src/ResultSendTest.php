<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\Log;
use ConectaEnte\Http\HttpExchange;
use ConectaEnte\Http\Response;
use ConectaEnte\Jobs\SendOpportunityJob;
use ConectaEnte\Jobs\SendResultJob;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Payload\ResultPayload;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ResultSender;
use ConectaEnte\Services\ResultState;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Doubles\RoutedTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

/**
 * O pedido de envio do resultado e o job que o leva ao CultBR, com a conciliação do envio sem resposta.
 */
class ResultSendTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    const STRUCTURE = ['id_formulario' => 109, 'id_formulario_versao' => 541, 'versao' => 12, 'secoes' => []];
    const NO_SPREADSHEET = ['possui_planilha' => false, 'campos' => []];
    const NOT_FOUND = ['detail' => 'Resultado publicado não encontrado'];

    private ?RoutedTransport $transport = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->mscache->delete('conectaente:result-form:1');
        $this->app->em->getConnection()->delete('job', ['name' => SendResultJob::SLUG]);
    }

    protected function tearDown(): void
    {
        Plugin::instance()->transport = null;
        Plugin::instance()->mode = null;
        $this->app->mscache->deleteAll();

        parent::tearDown();
    }

    function testACompleteResultIsQueuedAndTheTabShowsItIsSending()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(404, self::NOT_FOUND)]);

        $request = $this->resultService()->requestSend($opportunity);

        $this->assertTrue($request['queued']);
        $this->assertCount(1, $this->app->repo(Job::class)->findBy(['type' => SendResultJob::SLUG]));
        $state = $this->resultService()->stateOf($this->reloaded($opportunity));
        $this->assertSame(ResultState::SENDING, $state->state);
        $this->assertFalse($state->hasSendButton(), 'Em envio, o botão some.');
    }

    function testAPendingFormIsRefusedWithTheMissingQuestions()
    {
        $structure = json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/estrutura.json'), true);
        $opportunity = $this->readyEdital(structure: $structure);

        $request = $this->resultService()->requestSend($opportunity);

        $this->assertFalse($request['queued']);
        $this->assertNotEmpty($request['missing']);
        $this->assertNoResultJob();
    }

    function testAnEditalThatIsNotPublishedIsRefused()
    {
        $opportunity = $this->readyEdital(status: Opportunity::STATUS_DRAFT);

        $this->assertFalse($this->resultService()->requestSend($opportunity)['queued']);
        $this->assertNoResultJob();
    }

    function testASendInProgressRefusesTheSecondClick()
    {
        $opportunity = $this->readyEdital();
        Plugin::instance()->sendLogger()->openResultSend($opportunity);

        $request = $this->resultService()->requestSend($this->reloaded($opportunity));

        $this->assertFalse($request['queued']);
        $this->assertNoResultJob();
    }

    function testASendWhoseJobDiedBeforeThePostGivesTheButtonBack()
    {
        $opportunity = $this->readyEdital();
        $stale = Plugin::instance()->sendLogger()->openResultSend($opportunity);
        Plugin::instance()->sendLogger()->claimResultSend($stale);
        $this->backdate($stale, 'started_at');

        $state = $this->resultService()->stateOf($this->reloaded($opportunity));

        $this->assertTrue($state->hasSendButton(), 'Sem isso o edital fica preso em "em envio".');
        $this->assertSame(SendOutcome::ERROR, $this->reloadedLog($stale)->outcome);
        $this->assertTrue($this->resultService()->requestSend($this->reloaded($opportunity))['queued']);
    }

    function testAStartedSendInsideTheDeadlineStaysInProgress()
    {
        $opportunity = $this->readyEdital();
        $log = Plugin::instance()->sendLogger()->openResultSend($opportunity);
        Plugin::instance()->sendLogger()->claimResultSend($log);
        $this->backdate($log, 'started_at', '-9 minutes');

        $this->assertSame(ResultState::SENDING, $this->resultService()->stateOf($this->reloaded($opportunity))->state);
        $this->assertFalse($this->resultService()->requestSend($this->reloaded($opportunity))['queued']);
        $this->assertSame(SendOutcome::PENDING, $this->reloadedLog($log)->outcome);
        $this->assertNoResultJob();
    }

    function testASendThatNeverStartedGivesTheButtonBackAfterTheDeadline()
    {
        $opportunity = $this->readyEdital();
        $stale = Plugin::instance()->sendLogger()->openResultSend($opportunity);
        $this->backdate($stale, 'create_timestamp');

        $this->assertTrue($this->resultService()->stateOf($this->reloaded($opportunity))->hasSendButton());
    }

    function testASendWhoseJobDiedAfterThePostIsReconciledBeforeTheButtonComesBack()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(200, ['id_par_edital_resultado' => 20074])]);
        $this->backdate($this->postedSendWithoutOutcome($opportunity, seenResultId: 20073), 'started_at');

        $state = $this->resultService()->stateOf($this->reloaded($opportunity));

        $this->assertTrue($state->hasSendButton());
        $this->assertSame('20074', $this->reloaded($opportunity)->getMetadata(CultBrMetadata::RESULT_ID), 'O POST chegou: postar de novo publicaria dois resultados.');
    }

    function testASendWhoseJobDiedAfterThePostIsUncertainWhileThePublishedCannotBeRead()
    {
        $opportunity = $this->readyEdital(publishedReplies: [Response::failed('timeout')]);
        $this->backdate($this->postedSendWithoutOutcome($opportunity, seenResultId: 20073), 'started_at');

        $this->assertSame(ResultState::UNCERTAIN, $this->resultService()->stateOf($this->reloaded($opportunity))->state);
        $this->assertFalse($this->resultService()->requestSend($this->reloaded($opportunity))['queued']);
    }

    function testTheSameContentAlreadySentIsRefusedWithoutQueueing()
    {
        $opportunity = $this->readyEdital();
        $this->markAsSent($opportunity, $this->hashOf($opportunity), (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID));

        $request = $this->resultService()->requestSend($this->reloaded($opportunity));

        $this->assertFalse($request['queued']);
        $this->assertStringContainsString('Nada mudou', $request['message']);
        $this->assertNoResultJob();
    }

    function testTheSameContentSentForAnotherParEditalDoesNotBlock()
    {
        $opportunity = $this->readyEdital();
        $this->markAsSent($opportunity, $this->hashOf($opportunity), 1);

        $this->assertTrue($this->resultService()->requestSend($this->reloaded($opportunity))['queued'], 'O hash vale para o id do edital em que foi enviado.');
    }

    function testWhenThePublishedCannotBeReadTheResultSentHereOffersTheRetification()
    {
        $opportunity = $this->readyEdital(publishedReplies: [Response::failed('timeout')]);
        $this->markAsSent($opportunity, 'outro-conteudo', (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID));

        $state = $this->resultService()->stateOf($this->reloaded($opportunity));

        $this->assertSame(ResultState::SENT, $state->state);
        $this->assertTrue($state->hasSendButton());
    }

    function testAResultPublishedOutsideThePluginAlsoOffersTheRetification()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(200, ['id_par_edital_resultado' => 20073])]);

        $this->assertSame(ResultState::SENT, $this->resultService()->stateOf($opportunity)->state, 'O 20073 do 53781 não foi enviado por aqui.');
    }

    function testAPendingFormStillKnowsTheEditalHasAPublishedResult()
    {
        $structure = json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/estrutura.json'), true);
        $opportunity = $this->readyEdital(structure: $structure, publishedReplies: [RoutedTransport::json(200, ['id_par_edital_resultado' => 20073])]);

        $state = $this->resultService()->stateOf($opportunity);

        $this->assertSame(ResultState::PENDING, $state->state);
        $this->assertTrue($state->form['published'], 'Com pendência, o botão ainda diz Retificar.');
    }

    function testTheDispensedAttachmentsAreListedForTheTab()
    {
        $structure = json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/estrutura.json'), true);
        $opportunity = $this->readyEdital(structure: $structure);

        $this->assertContains(13930, $this->resultService()->stateOf($opportunity)->form['dispensed'], 'A planilha de TEC vem em linhas e não leva a marca de obrigatória.');
    }

    function testWithTheSpreadsheetTheEditalCompletesWithoutItsFile()
    {
        $opportunity = $this->editalWithSpreadsheetQuestion(hasSpreadsheet: true);

        $state = $this->resultService()->stateOf($opportunity);

        $this->assertSame(ResultState::COMPLETE, $state->state, 'Sem a dispensa, cobrar o arquivo e recusá-lo não deixa saída.');
        $this->assertSame([14170], $state->form['filledByLines']);
        $this->assertSame([14170], $state->form['unavailableAttachments']);
    }

    function testWithoutTheSpreadsheetTheEditalStillDemandsItsFile()
    {
        $state = $this->resultService()->stateOf($this->editalWithSpreadsheetQuestion(hasSpreadsheet: false));

        $this->assertSame(ResultState::PENDING, $state->state);
        $this->assertSame([14170], $state->form['missing']);
    }

    function testTheSpreadsheetQuestionTakesNoFileWhenTheLinesFillIt()
    {
        $opportunity = $this->editalWithSpreadsheetQuestion(hasSpreadsheet: true);
        $path = tempnam(sys_get_temp_dir(), 'anexo');

        $error = $this->resultService()->attach($opportunity, 14170, ['name' => 'planilha.csv', 'type' => 'text/csv', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => 1]);

        $this->assertStringContainsString('planilha de contemplados', (string) $error);
    }

    function testWithoutAPublishedResultTheButtonIsTheFirstSend()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(404, self::NOT_FOUND)]);

        $this->assertSame(ResultState::COMPLETE, $this->resultService()->stateOf($opportunity)->state);
    }

    function testTheRouteRefusesWhoDoesNotControlTheEdital()
    {
        $opportunity = $this->readyEdital();
        $this->login($this->userDirector->createUser());

        $this->assertSame(403, $this->send($this->requestFactory->POST('conectaente', 'resultSend', [$opportunity->id], [], ajax: true)));
    }

    function testWithoutAPublishedResultTheSendIsTheFinal()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(404, self::NOT_FOUND)]);

        $this->runSend($opportunity);

        $this->assertSame(ResultPayload::TYPE_FINAL, $this->postedBody()['id_tipo']);
        $reloaded = $this->reloaded($opportunity);
        $this->assertSame(ResultSender::STATUS_SUCCESS, $reloaded->getMetadata(CultBrMetadata::RESULT_SEND_STATUS));
        $this->assertSame('777', $reloaded->getMetadata(CultBrMetadata::RESULT_ID));
        $this->assertTrue(Plugin::instance()->resultSender()->hasSentResult($reloaded));
    }

    function testWithAPublishedResultTheSendIsTheRetification()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(200, ['id_par_edital_resultado' => 20073])]);

        $this->runSend($opportunity);

        $this->assertSame(ResultPayload::TYPE_CORRECTED, $this->postedBody()['id_tipo']);
    }

    /** @dataProvider indeterminateReadings */
    function testAnIndeterminateReadingSendsNothing(callable $reply)
    {
        $opportunity = $this->readyEdital(publishedReplies: [$reply()]);

        $log = $this->runSend($opportunity);

        $this->assertSame([], $this->transport->posts);
        $this->assertSame(SendOutcome::ERROR, $this->reloadedLog($log)->outcome);
        $this->assertFalse($this->reloadedLog($log)->posted);
    }

    static function indeterminateReadings(): array
    {
        return [
            '5xx' => [fn() => RoutedTransport::json(502, [])],
            '401' => [fn() => RoutedTransport::json(401, ['detail' => 'Token inválido'])],
            'sem resposta' => [fn() => Response::failed('timeout')],
            '404 com outra frase' => [fn() => RoutedTransport::json(404, ['detail' => 'Not Found'])],
        ];
    }

    function testASecondRunOfTheSameJobSendsNothing()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(404, self::NOT_FOUND)]);
        $log = Plugin::instance()->sendLogger()->openResultSend($opportunity);
        Plugin::instance()->sendLogger()->claimResultSend($log);

        Plugin::instance()->resultSender()->send($this->reloaded($opportunity), $this->reloadedLog($log));

        $this->assertSame([], $this->transport->posts, 'O core recria o job em processamento depois de 5 minutos.');
    }

    function testTheSendAbortsWhenTheEnvelopeExpiresBeforeThePost()
    {
        $opportunity = $this->readyEdital();
        $this->transport = $this->routes([
            function () {
                $this->app->em->getConnection()->executeStatement("UPDATE conectaente_log SET started_at = ? WHERE action = 'result' AND outcome = 'pending'", [(new \DateTime('-11 minutes'))->format('Y-m-d H:i:s')]);

                return RoutedTransport::json(200, ['id_par_edital_resultado' => 20073]);
            },
        ]);

        $this->runSend($opportunity);

        $this->assertSame([], $this->transport->posts);
    }

    function testAResultThatAppearedAfterAPostWithoutAnswerIsAdopted()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(200, ['id_par_edital_resultado' => 20074])]);
        $this->unansweredSend($opportunity, seenResultId: 20073);

        $this->runSend($opportunity);

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame([], $this->transport->posts, 'Postar de novo publicaria o mesmo resultado duas vezes.');
        $this->assertSame('20074', $reloaded->getMetadata(CultBrMetadata::RESULT_ID));
        $this->assertTrue(Plugin::instance()->resultSender()->isAlreadySent($reloaded, 'hash-da-tentativa'));
    }

    function testTheSamePublishedIdMeansThePostDidNotArrive()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(200, ['id_par_edital_resultado' => 20073])]);
        $this->unansweredSend($opportunity, seenResultId: 20073);

        $this->runSend($opportunity);

        $this->assertCount(1, $this->transport->posts, 'O 20073 já estava lá antes: é resultado de outro envio.');
    }

    function testAnIndeterminatePreviousReadingIsNeverAdopted()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(200, ['id_par_edital_resultado' => 20074])]);
        $this->unansweredSend($opportunity, seenResultId: null, seenResultRead: false);

        $this->runSend($opportunity);

        $this->assertCount(1, $this->transport->posts);
        $this->assertNotSame('20074', $this->reloaded($opportunity)->getMetadata(CultBrMetadata::RESULT_ID));
    }

    function testASendThatNeverPostedIsNeverAdopted()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(200, ['id_par_edital_resultado' => 20074])]);
        $this->unansweredSend($opportunity, seenResultId: 20073, posted: false);

        $this->runSend($opportunity);

        $this->assertCount(1, $this->transport->posts);
    }

    function testWhileThePublishedCannotBeReadTheOutcomeIsUncertain()
    {
        $opportunity = $this->readyEdital(publishedReplies: [Response::failed('timeout')]);
        $this->unansweredSend($opportunity, seenResultId: 20073);

        $this->assertSame(ResultState::UNCERTAIN, $this->resultService()->stateOf($this->reloaded($opportunity))->state);
        $this->assertFalse($this->resultService()->requestSend($this->reloaded($opportunity))['queued']);
    }

    function testADeterminateReadingSettlesTheUncertainOutcomeOnce()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(200, ['id_par_edital_resultado' => 20073])]);
        $log = $this->unansweredSend($opportunity, seenResultId: 20073);

        $this->assertSame(ResultState::SENT, $this->resultService()->stateOf($this->reloaded($opportunity))->state, 'O 20073 publicado mantém o Retificar.');
        $this->assertNotNull($this->reloadedLog($log)->reconciledAt);
        $this->assertNull(Plugin::instance()->resultSender()->unsettledSend($this->reloaded($opportunity)), 'Conciliado uma vez, a aba não lê o publicado a cada carga.');
    }

    function testACreatedResultWithoutIdRecoversItFromThePublished()
    {
        $opportunity = $this->readyEdital();
        $this->transport = $this->routes([RoutedTransport::json(404, self::NOT_FOUND), RoutedTransport::json(200, ['id_par_edital_resultado' => 20090])], RoutedTransport::json(201, ['message' => 'ok']));

        $this->runSend($opportunity);

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame(ResultSender::STATUS_SUCCESS, $reloaded->getMetadata(CultBrMetadata::RESULT_SEND_STATUS), 'Recusar faria o clique seguinte publicar de novo.');
        $this->assertSame('20090', $reloaded->getMetadata(CultBrMetadata::RESULT_ID));
    }

    function testARefusedRowIsKeptWithTheRegistrationAndWithoutTheReceivedValue()
    {
        $opportunity = $this->readyEdital();
        $this->transport = $this->routes([RoutedTransport::json(404, self::NOT_FOUND)], RoutedTransport::json(422, [
            'message' => 'Há linhas inválidas. Nenhuma linha foi salva.',
            'total_inconsistencias' => 1,
            'inconsistencias' => [['linha' => 2, 'indice' => 0, 'coluna' => 'cpf', 'regra' => 'cpf_valido', 'valor_recebido' => '52998224725']],
        ]));

        $this->runSend($opportunity);

        $reloaded = $this->reloaded($opportunity);
        $errors = json_decode(json_encode($reloaded->{CultBrMetadata::RESULT_SEND_ERRORS}), true);
        $this->assertSame(ResultSender::STATUS_REJECTED, $reloaded->getMetadata(CultBrMetadata::RESULT_SEND_STATUS));
        $this->assertSame('cpf', $errors['rows'][0]['coluna']);
        $this->assertArrayNotHasKey('valor_recebido', $errors['rows'][0], 'Pode ser o CPF de um contemplado.');
        $this->assertArrayNotHasKey('indice', $errors['rows'][0]);
        $this->assertSame(1, $errors['totalRows']);
    }

    function testAnUnauthorizedSendIsRejectedWithTheReason()
    {
        $opportunity = $this->readyEdital();
        $this->transport = $this->routes([RoutedTransport::json(404, self::NOT_FOUND)], RoutedTransport::json(401, ['detail' => 'Token inválido']));

        $log = $this->runSend($opportunity);

        $this->assertSame(ResultSender::STATUS_REJECTED, $this->reloaded($opportunity)->getMetadata(CultBrMetadata::RESULT_SEND_STATUS));
        $this->assertSame(SendOutcome::REJECTED, $this->reloadedLog($log)->outcome);
    }

    function testAServerErrorIsUncertainAndGoesToTheNextReconciliation()
    {
        $opportunity = $this->readyEdital();
        $this->transport = $this->routes([RoutedTransport::json(404, self::NOT_FOUND)], RoutedTransport::json(503, []));

        $log = $this->runSend($opportunity);

        $this->assertSame(ResultSender::STATUS_UNCERTAIN, $this->reloaded($opportunity)->getMetadata(CultBrMetadata::RESULT_SEND_STATUS));
        $this->assertSame(SendOutcome::ERROR, $this->reloadedLog($log)->outcome);
        $this->assertSame($log->id, Plugin::instance()->resultSender()->unsettledSend($this->reloaded($opportunity))?->id);
    }

    function testTheBodyOfTheSentLinesIsNotKeptInTheHistory()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(404, self::NOT_FOUND)]);

        $log = $this->runSend($opportunity);

        $attempt = $this->app->repo(\ConectaEnte\Entities\LogAttempt::class)->findOneBy(['log' => $log->id], ['id' => 'DESC']);
        $this->assertSame(201, $attempt->httpStatus);
        $this->assertSame(SendOutcome::SUCCESS, $this->reloadedLog($log)->outcome);
    }

    function testTheCommitteeDocumentIsMaskedInTheHistoryButSentInFull()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(404, self::NOT_FOUND)]);
        $this->app->hook('conectaente.resultPayload', function ($opportunity, &$payload) {
            $payload['respostas'][] = ['id_par_questao' => 13933, 'resposta' => ['resposta' => [['nome' => 'Fulana de Tal Teste', 'cpf' => '529.982.247-25']]]];
        });

        try {
            $log = $this->runSend($opportunity);
        } finally {
            $this->app->clearHooks('conectaente.resultPayload');
        }

        $attempt = $this->app->repo(\ConectaEnte\Entities\LogAttempt::class)->findOneBy(['log' => $log->id], ['id' => 'DESC']);
        $this->assertStringNotContainsString('529.982.247-25', (string) $attempt->requestPayload);
        $this->assertStringContainsString('Fulana de Tal Teste', (string) $attempt->requestPayload);
        $this->assertSame('529.982.247-25', $this->postedBody()['respostas'][0]['resposta']['resposta'][0]['cpf']);
    }

    function testInDevModeTheSendIsSimulatedWithoutKeepingTheHash()
    {
        $opportunity = $this->readyEdital(publishedReplies: [RoutedTransport::json(404, self::NOT_FOUND)]);
        Plugin::instance()->mode = Plugin::MODE_DEV;

        $log = $this->runSend($opportunity);

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame([], $this->transport->posts);
        $this->assertSame(ResultSender::STATUS_SIMULATED, $reloaded->getMetadata(CultBrMetadata::RESULT_SEND_STATUS));
        $this->assertFalse(Plugin::instance()->resultSender()->hasSentResult($reloaded));
        $this->assertSame(SendOutcome::SIMULATED, $this->reloadedLog($log)->outcome);
    }

    function testANewParEditalIdClearsTheResultState()
    {
        $opportunity = $this->readyEdital();
        $this->markAsSent($opportunity, 'conteudo', (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID));
        Plugin::instance()->transport = FakeTransport::replying(200, ['id_par_edital' => 99887766]);

        $jobType = new SendOpportunityJob(SendOpportunityJob::SLUG);
        $job = new Job($jobType);
        $job->opportunityId = $opportunity->id;
        $job->attempt = 1;
        $jobType->_execute($job);

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame('99887766', $reloaded->getMetadata(CultBrMetadata::PAR_EDITAL_ID));
        $this->assertNull($reloaded->getMetadata(CultBrMetadata::RESULT_ID), 'O resultado era do edital antigo.');
        $this->assertNull($reloaded->getMetadata(CultBrMetadata::RESULT_SEND_STATUS));
    }

    function testAnExpiredSendIsToldInTheTab()
    {
        $opportunity = $this->readyEdital();
        $log = Plugin::instance()->sendLogger()->openResultSend($this->reloaded($opportunity));
        $this->backdate($log, 'create_timestamp');

        $lastSend = $this->resultService()->lastSend($this->reloaded($opportunity));

        $this->assertSame(ResultSender::STATUS_ERROR, $lastSend['status']);
        $this->assertStringContainsString('não saiu no prazo', $lastSend['outcome']['message']);
    }

    function testASendStillInTimeIsNotTold()
    {
        $opportunity = $this->readyEdital();
        Plugin::instance()->sendLogger()->openResultSend($this->reloaded($opportunity));

        $this->assertNull($this->resultService()->lastSend($this->reloaded($opportunity)));
    }

    function testAnExpiredSendThatMayHaveReachedTheCultBrIsNotCalledUnsent()
    {
        $opportunity = $this->readyEdital();
        $this->backdate($this->postedSendWithoutOutcome($opportunity, 20073), 'started_at');

        $this->assertStringNotContainsString('Nada foi enviado', (string) ($this->resultService()->lastSend($this->reloaded($opportunity))['outcome']['message'] ?? ''));
    }

    private function readyEdital(int $status = Opportunity::STATUS_ENABLED, ?array $structure = null, array $publishedReplies = []): Opportunity
    {
        $opportunity = $this->sealedOpportunity($status);
        $document = $this->resolveFederativeEntity($opportunity)->document;
        $opportunity = $this->reloaded($opportunity);
        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_ID, (string) random_int(1, 9999999));
        $opportunity->setMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID, '1');
        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_FEDERATIVE_ENTITY_DOCUMENT, $document);
        $opportunity->saveMetadata();

        $lastPhase = $opportunity->lastPhase;
        $lastPhase->publishedRegistrations = true;
        $lastPhase->save(true);

        Plugin::instance()->mode = Plugin::MODE_LIVE;
        $this->transport = $this->routes($publishedReplies ?: [RoutedTransport::json(404, self::NOT_FOUND)], structure: $structure);

        return $this->reloaded($opportunity);
    }

    private function editalWithSpreadsheetQuestion(bool $hasSpreadsheet): Opportunity
    {
        $question = ['id_questao_etapa' => 14170, 'tipo_questao' => 'anexo', 'tag' => 'upload_planilha_csv_1_5', 'requerida' => true, 'config' => ['formatos' => ['.csv']]];
        $opportunity = $this->readyEdital(structure: ['secoes' => [['etapas' => [['questoes' => [$question]]]]]] + self::STRUCTURE);
        Plugin::instance()->transport = new RoutedTransport([
            'GET /formularios/estrutura' => [RoutedTransport::json(200, ['secoes' => [['etapas' => [['questoes' => [$question]]]]]] + self::STRUCTURE)],
            'GET /resultado/modelo-planilha' => [RoutedTransport::json(200, ['possui_planilha' => $hasSpreadsheet, 'campos' => []])],
            'GET /resultado/publicado' => [RoutedTransport::json(404, self::NOT_FOUND)],
        ]);

        return $opportunity;
    }

    private function routes(array $publishedReplies, ?Response $postReply = null, ?array $structure = null): RoutedTransport
    {
        $transport = new RoutedTransport([
            'GET /formularios/estrutura' => [RoutedTransport::json(200, $structure ?? self::STRUCTURE)],
            'GET /resultado/modelo-planilha' => [RoutedTransport::json(200, self::NO_SPREADSHEET)],
            'GET /resultado/publicado' => $publishedReplies,
            'POST /resultado' => [$postReply ?? RoutedTransport::json(201, ['id_par_edital_resultado' => 777])],
        ]);
        Plugin::instance()->transport = $transport;

        return $transport;
    }

    private function runSend(Opportunity $opportunity): Log
    {
        $log = Plugin::instance()->sendLogger()->openResultSend($this->reloaded($opportunity));
        $jobType = new SendResultJob(SendResultJob::SLUG);
        $job = new Job($jobType);
        $job->opportunityId = $opportunity->id;
        $job->logUuid = $log->uuid;

        $jobType->_execute($job);

        return $log;
    }

    private function unansweredSend(Opportunity $opportunity, ?int $seenResultId, bool $seenResultRead = true, bool $posted = true): Log
    {
        $logger = Plugin::instance()->sendLogger();
        $log = $logger->openResultSend($this->reloaded($opportunity));
        $logger->claimResultSend($log);
        $logger->keepResultContext($log, $seenResultId, $seenResultRead, 'hash-da-tentativa', ResultPayload::TYPE_CORRECTED, '2026-11-22T10:00:00-03:00');

        if ($posted) {
            $logger->confirmResultPost($log);
        }

        $logger->recordAttempts($log, [new HttpExchange(endpoint: 'https://cultbr.example/resultado', errorMessage: 'timeout')], SendOutcome::ERROR, 1, 1);
        $logger->finish($log, SendOutcome::ERROR);

        return $log;
    }

    private function postedSendWithoutOutcome(Opportunity $opportunity, int $seenResultId): Log
    {
        $logger = Plugin::instance()->sendLogger();
        $log = $logger->openResultSend($this->reloaded($opportunity));
        $logger->claimResultSend($log);
        $logger->keepResultContext($log, $seenResultId, true, 'hash-da-tentativa', ResultPayload::TYPE_CORRECTED, '2026-11-22T10:00:00-03:00');
        $logger->confirmResultPost($log);

        return $log;
    }

    private function backdate(Log $log, string $column, string $age = '-11 minutes'): void
    {
        $this->app->em->getConnection()->update('conectaente_log', [$column => (new \DateTime($age))->format('Y-m-d H:i:s')], ['id' => $log->id]);
    }

    private function markAsSent(Opportunity $opportunity, string $hash, int $parEditalId): void
    {
        $opportunity = $this->reloaded($opportunity);
        $opportunity->{CultBrMetadata::RESULT_SENT_HASH} = ['hash' => $hash, 'parEditalId' => $parEditalId];
        $opportunity->{CultBrMetadata::RESULT_ID} = '20073';
        $opportunity->{CultBrMetadata::RESULT_SEND_STATUS} = ResultSender::STATUS_SUCCESS;
        $opportunity->saveMetadata();
    }

    private function hashOf(Opportunity $opportunity): string
    {
        $state = $this->resultService()->stateOf($this->reloaded($opportunity));

        return Plugin::instance()->resultPayload()->build($this->reloaded($opportunity), new \ConectaEnte\Services\ResultForm($state->structure), ResultPayload::TYPE_FINAL)['hash'];
    }

    private function postedBody(): array
    {
        $this->assertCount(1, $this->transport->posts);

        return $this->transport->posts[0]['body'];
    }

    private function assertNoResultJob(): void
    {
        $this->assertSame([], $this->app->repo(Job::class)->findBy(['type' => SendResultJob::SLUG]));
    }

    private function reloadedLog(Log $log): Log
    {
        $this->app->em->clear();

        return $this->app->repo(Log::class)->find($log->id);
    }

    private function resultService(): \ConectaEnte\Services\ResultService
    {
        return Plugin::instance()->resultService();
    }
}
