<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Jobs\SendOpportunityJob;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Http\ParInformationResult;
use ConectaEnte\Plugin;
use ConectaEnte\Services\OpportunitySender;
use ConectaEnte\Services\ParInformationService;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\App;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;
use Monolog\Handler\TestHandler;
use RuntimeException;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Doubles\QueueTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\ConectaEnte\Traits\SendQueue;

/**
 * O job que envia o edital selado ao CultBR, com retentativa só para indisponibilidade.
 */
class SendOpportunityJobTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use SendQueue;

    function testSuccessIsRecordedAndDoesNotRequeue()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(200, ['id_par_edital' => 1]);

        $this->executeSend($opportunity->id);

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame('success', $reloaded->getMetadata(CultBrMetadata::SEND_STATUS));
        $this->assertSame([], $this->enqueuedSendJobs());
        $sendAt = (string) $reloaded->getMetadata(CultBrMetadata::SEND_AT);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $sendAt, 'O formato é o que o histórico vai ler.');
        $this->assertEqualsWithDelta(time(), strtotime($sendAt), 5, 'A data é a do envio que acabou de acontecer, não uma data fixa nem a de publicação.');
    }

    function testClientErrorIsRejectedAndDoesNotRetry()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(422, ['detail' => 'ente_federado obrigatório']);

        $this->executeSend($opportunity->id);

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame('rejected', $reloaded->getMetadata(CultBrMetadata::SEND_STATUS));
        $this->assertSame('ente_federado obrigatório', $reloaded->getMetadata(CultBrMetadata::SEND_REASON));
        $this->assertSame([], $this->enqueuedSendJobs());
    }

    function testServerErrorRetriesWithDelay()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(500, 'Internal Server Error');

        $this->executeSend($opportunity->id, attempt: 1);

        $jobs = $this->enqueuedSendJobs();
        $this->assertCount(1, $jobs);
        $this->assertSame(2, (int) $jobs[0]->attempt);
        $this->assertGreaterThanOrEqual(
            Plugin::instance()->sendRetryDelaySeconds(),
            $jobs[0]->nextExecutionTimestamp->getTimestamp() - time() + 1,
        );
        $this->assertNull($this->reloaded($opportunity)->getMetadata(CultBrMetadata::SEND_STATUS), 'Indisponível não é desfecho final: nada é gravado enquanto pode retentar.');
    }

    // o número literal, não o acessor: comparar com sendMaxAttempts() tornaria a asserção tautológica
    function testTheSecondAttemptStillRetriesWithoutRecordingAnOutcome()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(500, 'Internal Server Error');

        $this->executeSend($opportunity->id, attempt: 2);

        $jobs = $this->enqueuedSendJobs();
        $this->assertCount(1, $jobs, 'Com limite de três, a segunda tentativa ainda reagenda.');
        $this->assertSame(3, (int) $jobs[0]->attempt);
        $this->assertNull($this->reloaded($opportunity)->getMetadata(CultBrMetadata::SEND_STATUS), 'Enquanto há tentativa pela frente, nada é gravado.');
    }

    // a retentativa troca de tentativa, então é ela que prova o dedupe: por oportunidade, não por tentativa
    function testTheRetryReplacesTheQueuedJobInsteadOfAddingOne()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        Plugin::instance()->mode = Plugin::MODE_LIVE;
        Plugin::instance()->transport = FakeTransport::replying(500, 'Internal Server Error');
        $this->assertCount(1, $this->enqueuedSendJobs(), 'Premissa do teste: o gatilho deixou um job de primeira tentativa na fila.');

        $this->executeSend($opportunity->id, attempt: 1);

        $jobs = $this->enqueuedSendJobs();
        $this->assertCount(1, $jobs, 'A retentativa substitui o job do mesmo edital; somar linhas faria a fila crescer a cada falha.');
        $this->assertSame(2, (int) $jobs[0]->attempt);
    }

    function testTheRetryIsScheduledForTheConfiguredDelay()
    {
        // o literal trava o valor acordado; a janela, o mecanismo. Só a constante seria tautológico
        $this->assertSame(30, Plugin::DEFAULT_SEND_RETRY_DELAY_SECONDS, 'O atraso padrão acordado é de trinta segundos.');

        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(500, 'Internal Server Error');
        $earliest = time() + Plugin::DEFAULT_SEND_RETRY_DELAY_SECONDS;

        $this->executeSend($opportunity->id, attempt: 1);

        $latest = time() + Plugin::DEFAULT_SEND_RETRY_DELAY_SECONDS;
        $jobs = $this->enqueuedSendJobs();
        $this->assertCount(1, $jobs, 'Premissa do teste: a retentativa precisa ter sido agendada.');

        $scheduled = $jobs[0]->nextExecutionTimestamp->getTimestamp();
        $this->assertGreaterThanOrEqual($earliest, $scheduled, 'Atraso menor que o configurado bate na API antes da hora.');
        $this->assertLessThanOrEqual($latest, $scheduled, 'Atraso maior adia o edital sem motivo; a janela é a duração da própria execução.');
    }

    function testGivesUpAfterTheAttemptLimit()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::unreachable();

        $this->executeSend($opportunity->id, attempt: Plugin::instance()->sendMaxAttempts());

        $this->assertSame([], $this->enqueuedSendJobs(), 'Esgotadas as tentativas, a fila não pode continuar reagendando para sempre.');
        $this->assertSame('error', $this->reloaded($opportunity)->getMetadata(CultBrMetadata::SEND_STATUS), 'Não respondeu é falha, não recusa: `rejected` é só para o que o CultBR negou.');
    }

    function testExhaustionKeepsTheHttpStatusInTheRecordedReason()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(500, 'Internal Server Error');

        $this->executeSend($opportunity->id, attempt: Plugin::instance()->sendMaxAttempts());

        $reason = (string) $this->reloaded($opportunity)->getMetadata(CultBrMetadata::SEND_REASON);
        $this->assertStringContainsString('500', $reason, 'Sem o status, erro do CultBR fica indistinguível de queda de conexão.');
    }

    function testExhaustionWithoutResponseSaysSoWithoutLeakingTheTransportError()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::unreachable();

        $this->executeSend($opportunity->id, attempt: Plugin::instance()->sendMaxAttempts());

        $reason = (string) $this->reloaded($opportunity)->getMetadata(CultBrMetadata::SEND_REASON);
        $this->assertStringNotContainsString('Could not resolve host', $reason, 'O motivo sai na API sem sessão: erro de transporte revela host e DNS.');
        $this->assertStringContainsString('não respondeu', $reason, 'Sem resposta não há status: "HTTP 0" seria invenção.');
    }

    function testDevModeRecordsSimulatedWithoutTouchingTheTransport()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->mode = Plugin::MODE_DEV;
        Plugin::instance()->transport = $transport = FakeTransport::unreachable();

        $this->executeSend($opportunity->id);

        $this->assertSame([], $transport->requestedUrls, 'Modo simulado não pode chamar a API real.');
        $this->assertSame('simulated', $this->reloaded($opportunity)->getMetadata(CultBrMetadata::SEND_STATUS));
    }

    // a faixa na pagina nao alcanca quem drena a fila: o worker precisa deixar rastro do que nao enviou
    function testTheSimulatedSendLeavesATraceInTheLog()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->mode = Plugin::MODE_DEV;
        Plugin::instance()->transport = FakeTransport::unreachable();
        $log = $this->captureLog();

        $this->executeSend($opportunity->id);

        $this->assertTrue(
            $log->hasWarningThatContains("edital {$opportunity->id} não foi enviado"),
            'Sem o registro, uma instalação em dev não envia nada e o único aviso é uma faixa que o worker não vê.',
        );
        $this->assertFalse($log->hasErrorRecords(), 'Simular é o comportamento pedido pela configuração, não incidente para investigar.');
    }

    function testIneligibleOpportunityIsSkippedWithoutCallingTheTransport()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        Plugin::instance()->mode = Plugin::MODE_LIVE;
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, []);

        $finished = $this->executeSend($opportunity->id);

        $this->assertTrue($finished);
        $this->assertSame([], $transport->requestedUrls);
    }

    function testAnOpportunityThatNoLongerExistsIsSkippedWithoutNoise()
    {
        $this->loginAsSaasSuperAdmin();
        Plugin::instance()->mode = Plugin::MODE_LIVE;
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, []);
        $handler = $this->captureLog();

        $finished = $this->executeSend($this->vanishedOpportunityId());

        $this->assertTrue($finished);
        $this->assertSame([], $transport->requestedUrls, 'Oportunidade que sumiu não pode virar PUT ao CultBR.');
        $this->assertFalse($handler->hasErrorRecords(), 'Edital apagado entre o enfileiramento e a execução é rotina, não incidente para investigar no log.');
    }

    function testATransportExceptionIsLoggedAndTheJobStillEnds()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = QueueTransport::replying(new RuntimeException('estouro no envio'));
        $handler = $this->captureLog();

        $finished = $this->executeSend($opportunity->id);

        $this->assertTrue($finished, 'Job que não termina fica preso em processamento para sempre.');
        $this->assertTrue(
            $handler->hasErrorThatContains("exceção ao enviar a oportunidade {$opportunity->id}"),
            'Sem o log, a exceção fica só na frase estável do metadado e ninguém descobre a causa.',
        );
        $this->assertTrue($handler->hasErrorThatContains('estouro no envio'), 'O log é o lugar onde a mensagem da exceção pode aparecer.');
    }

    // o erro do curl nomeia host e DNS: o log é o único lugar onde ele pode aparecer
    function testUnavailabilityPutsTheTransportErrorInTheLogOnly()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::unreachable();
        $handler = $this->captureLog();

        $this->executeSend($opportunity->id, attempt: Plugin::instance()->sendMaxAttempts());

        $this->assertTrue(
            $handler->hasErrorThatContains('Could not resolve host'),
            'Sem este registro, uma indisponibilidade não deixa rastro de causa em lugar nenhum.',
        );

        $reason = (string) $this->reloaded($opportunity)->getMetadata(CultBrMetadata::SEND_REASON);
        $this->assertNotSame('', $reason, 'Premissa do teste: motivo vazio tornaria a asserção seguinte trivial.');
        $this->assertStringNotContainsString('Could not resolve host', $reason, 'E o que está no log não pode estar também no metadado, que sai na API sem sessão.');
    }

    // falhar ao decidir não é falhar ao enviar: gravar desfecho aqui publicaria um envio que não houve
    function testAnExceptionWhileDecidingIsLoggedWithoutRecordingAnOutcome()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->parInformationService = new class extends ParInformationService {
            public function __construct()
            {
            }

            public function cachedForOpportunity(Opportunity $opportunity): ?ParInformationResult
            {
                throw new RuntimeException('estouro ao decidir');
            }
        };
        $handler = $this->captureLog();

        $finished = $this->executeSend($opportunity->id);

        $this->assertTrue($finished, 'Exceção ao decidir não pode prender a linha do job em processamento.');
        $this->assertTrue($handler->hasErrorThatContains('exceção ao decidir'), 'A falha precisa deixar rastro no log.');
        $this->assertNull(
            $this->reloaded($opportunity)->getMetadata(CultBrMetadata::SEND_STATUS),
            'O envio nem foi tentado: gravar desfecho diria ao gestor, em metadado público, que o edital falhou no CultBR.',
        );
    }

    function testATransportExceptionIsRecordedAsFailure()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = QueueTransport::replying(new RuntimeException('estouro no envio'));

        $this->executeSend($opportunity->id);

        $reloaded = $this->reloaded($opportunity);
        $reason = (string) $reloaded->getMetadata(CultBrMetadata::SEND_REASON);
        $this->assertSame('error', $reloaded->getMetadata(CultBrMetadata::SEND_STATUS), 'Envio que morre por exceção não pode ficar indistinguível de nunca tentado.');
        $this->assertNotSame('', $reason);
        $this->assertStringNotContainsString('estouro no envio', $reason, 'O motivo sai na API sem sessão: mensagem de exceção fica no log, não no metadado.');
    }

    function testAnErrorThatTheCoreWouldNotCatchIsAlsoRecorded()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = QueueTransport::replying(new \TypeError('tipo errado no envio'));

        $finished = $this->executeSend($opportunity->id);

        $this->assertTrue($finished, 'O catch do core pega só Exception: um Error escaparia e derrubaria o worker.');
        $this->assertSame('error', $this->reloaded($opportunity)->getMetadata(CultBrMetadata::SEND_STATUS));
    }

    function testEachOpportunityHasItsOwnJobId()
    {
        $first = $this->liveSealedOpportunity();
        $second = $this->liveSealedOpportunity();
        $jobType = new SendOpportunityJob(SendOpportunityJob::SLUG);

        $this->assertNotSame(
            $jobType->generateId(SendOpportunityJob::dataFor($first), 'now', '', 1),
            $jobType->generateId(SendOpportunityJob::dataFor($second), 'now', '', 1),
        );
    }

    // selar a publicada dispara o gatilho: sem tirar esse job da fila, os testes contariam o envio errado
    // o payload e conferido ANTES do desvio de modo: em dev, pular a conferencia esconderia
    // o campo faltando justamente no ambiente que existe para encontra-lo
    function testAnIncompletePayloadIsRefusedInDevModeInsteadOfSimulated()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        Plugin::instance()->mode = Plugin::MODE_DEV;
        Plugin::instance()->transport = $transport = FakeTransport::unreachable();
        $this->removeFromPayload('numero_previsto_vagas');

        $this->executeSend($opportunity->id);

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame(SendOutcome::ERROR, $reloaded->getMetadata(CultBrMetadata::SEND_STATUS), 'Payload incompleto não é simulação bem-sucedida.');
        $this->assertStringContainsString('Total de vagas', (string) $reloaded->getMetadata(CultBrMetadata::SEND_REASON), 'O motivo nomeia o campo pelo rótulo de tela.');
        $this->assertSame([], $transport->requestedUrls, 'E nada vai à rede.');
    }

    function testAnIncompletePayloadIsRefusedBeforeReachingTheApi()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, ['id_par_edital' => 1]);
        $this->removeFromPayload('pdf_edital');

        $this->executeSend($opportunity->id);

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame(SendOutcome::ERROR, $reloaded->getMetadata(CultBrMetadata::SEND_STATUS));
        $this->assertStringContainsString('Regulamento', (string) $reloaded->getMetadata(CultBrMetadata::SEND_REASON));
        $this->assertSame([], $transport->requestedUrls, 'Conferir depois de enviar não evitaria a recusa do CultBR.');
    }

    function testACompletePayloadStillReachesTheApiInLiveMode()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, ['id_par_edital' => 1]);

        $this->executeSend($opportunity->id);

        $this->assertCount(1, $transport->requestedUrls, 'Premissa: a conferência não pode barrar edital completo.');
        $this->assertSame(SendOutcome::SUCCESS, $this->reloaded($opportunity)->getMetadata(CultBrMetadata::SEND_STATUS));
    }

    // o motivo vai para metadado publico: muitas pendencias indicam defeito no build, nao edital
    // incompleto — o gate barraria antes. Ai o muro de texto fica no log, e no metadado vai o resumo
    function testManyPendingFieldsAreSummarizedInThePublicReasonAndWholeInTheLog()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(200, []);
        $log = $this->captureLog();
        $this->emptyThePayload();

        $this->executeSend($opportunity->id);

        $reason = (string) $this->reloaded($opportunity)->getMetadata(CultBrMetadata::SEND_REASON);
        $this->assertStringContainsString('E outros', $reason, 'Trinta frases num metadado público não ajudam ninguém.');
        $this->assertLessThan(400, mb_strlen($reason), "O motivo público ficou com {$reason}");
        $this->assertTrue($log->hasErrorThatContains('payload do edital'), 'O detalhe completo tem que estar no log, onde cabe.');
    }

    // divida declarada ao fechar a adequacao do PR 34: o try/catch interno do recordFailure nao tinha
    // como ser coberto sem ponto de injecao no sender. Apaga-lo prendia a linha do job em processamento
    function testTheJobSurvivesASenderThatAlsoFailsToRecordTheOutcome()
    {
        $opportunity = $this->liveSealedOpportunity();
        $log = $this->captureLog();
        Plugin::instance()->opportunitySender = new class (Plugin::instance(), Plugin::instance()->sealedOpportunity()) extends OpportunitySender {
            public function send(Opportunity $opportunity): SendOutcome
            {
                throw new RuntimeException('estouro no envio');
            }

            public function recordFailure(Opportunity $opportunity): void
            {
                throw new RuntimeException('estouro ao gravar o desfecho');
            }
        };

        try {
            $finished = $this->executeSend($opportunity->id);
        } finally {
            Plugin::instance()->opportunitySender = null;
        }

        $this->assertTrue($finished, 'Job que não termina fica preso em processamento para sempre.');
        $this->assertTrue($log->hasErrorThatContains('estouro no envio'), 'A causa primeira vai ao log.');
        $this->assertTrue($log->hasErrorThatContains('falha ao registrar o desfecho'), 'E a falha do próprio registro também, senão ela desaparece.');
    }

    // desligado por flag no tearDown, nao por clearHooks(): o clear() do core nao invalida o _hookCache
    private function removeFromPayload(string $contractKey): void
    {
        $this->removingFromPayload = true;
        $this->app->hook('conectaente.opportunityPayload', function ($opportunity, &$payload) use ($contractKey) {
            if ($this->removingFromPayload) {
                unset($payload[$contractKey]);
            }
        });
    }

    private function emptyThePayload(): void
    {
        $this->removingFromPayload = true;
        $this->app->hook('conectaente.opportunityPayload', function ($opportunity, &$payload) {
            if ($this->removingFromPayload) {
                $payload = [];
            }
        });
    }

    private bool $removingFromPayload = false;

    private function liveSealedOpportunity(): Opportunity
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        Plugin::instance()->mode = Plugin::MODE_LIVE;
        $this->purgeSendJobs();

        return $opportunity;
    }

    private ?TestHandler $logHandler = null;

    private function captureLog(): TestHandler
    {
        App::i()->log->pushHandler($this->logHandler = new TestHandler());

        return $this->logHandler;
    }

    // o logger da App sobrevive ao teste: o handler precisa sair junto com ele
    protected function tearDown(): void
    {
        $this->removingFromPayload = false;

        if ($this->logHandler) {
            App::i()->log->popHandler();
            $this->logHandler = null;
        }

        Plugin::instance()->parInformationService = null;
        Plugin::instance()->transport = null;
        Plugin::instance()->mode = null;

        parent::tearDown();
    }

    private function executeSend(int $opportunityId, int $attempt = 1): bool
    {
        $jobType = new SendOpportunityJob(SendOpportunityJob::SLUG);
        $job = new Job($jobType);
        $job->opportunityId = $opportunityId;
        $job->attempt = $attempt;

        return $jobType->_execute($job);
    }

    private function vanishedOpportunityId(): int
    {
        $last = $this->app->repo(Opportunity::class)->findBy([], ['id' => 'DESC'], 1);

        return $last ? $last[0]->id + 1 : 1;
    }
}
