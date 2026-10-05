<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Jobs\SendOpportunityJob;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use MapasCulturais\App;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;
use Monolog\Handler\TestHandler;
use RuntimeException;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Doubles\QueueTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * O job que envia o edital selado ao CultBR, com retentativa só para indisponibilidade.
 */
class SendOpportunityJobTest extends TestCase
{
    use PublicationRequirementsFixtures;

    function testSuccessIsRecordedAndDoesNotRequeue()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(200, ['id_par_edital' => 1]);

        $this->executeSend($opportunity->id);

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame('success', $reloaded->getMetadata(CultBrMetadata::SEND_STATUS));
        $this->assertSame([], $this->enqueuedSendJobs());
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
    private function liveSealedOpportunity(): Opportunity
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        Plugin::instance()->mode = Plugin::MODE_LIVE;
        $this->purgeSendJobs();

        return $opportunity;
    }

    private function purgeSendJobs(): void
    {
        $this->app->em->getConnection()->delete('job', ['name' => SendOpportunityJob::SLUG]);
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
        if ($this->logHandler) {
            App::i()->log->popHandler();
            $this->logHandler = null;
        }

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

    /** @return Job[] */
    private function enqueuedSendJobs(): array
    {
        return $this->app->repo(Job::class)->findBy(['type' => SendOpportunityJob::SLUG]);
    }

    private function vanishedOpportunityId(): int
    {
        $last = $this->app->repo(Opportunity::class)->findBy([], ['id' => 'DESC'], 1);

        return $last ? $last[0]->id + 1 : 1;
    }
}
