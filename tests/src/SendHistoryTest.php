<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\Log;
use ConectaEnte\Entities\LogAttempt;
use ConectaEnte\Jobs\SendOpportunityJob;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Services\OpportunitySender;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;
use RuntimeException;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\ConectaEnte\Traits\SendQueue;

/**
 * O que o envio deixa no histórico, do primeiro disparo ao esgotamento das tentativas.
 */
class SendHistoryTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use SendQueue;

    function testAnAcceptedSendClosesTheEnvelopeAsSuccess()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(200, ['id_par_edital' => 53781], ['x-request-id' => 'req-7'], 120);

        $this->executeSend($opportunity->id);

        $log = $this->logOf($opportunity);
        $this->assertSame(SendOutcome::SUCCESS, $log->outcome);
        $tentativa = $this->attemptsOf($log)[0];
        $this->assertSame(200, $tentativa->httpStatus);
        $this->assertSame('req-7', $tentativa->requestId, 'O x-request-id é o que correlaciona a linha com o log do outro lado.');
        $this->assertSame(120, $tentativa->durationMs);
        $this->assertStringContainsString('53781', $tentativa->responseBody);
        $this->assertSame(53781, $log->parEditalId, 'O id do edital no CultBR fica em coluna própria: sem ele, correlacionar os dois lados obriga a abrir o payload.');
    }

    function testTheEnvelopeAndTheOpportunityAgreeOnTheOutcome()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(200, ['id_par_edital' => 53781]);

        $this->executeSend($opportunity->id);

        $this->assertSame(
            $this->reloaded($opportunity)->getMetadata(CultBrMetadata::SEND_STATUS),
            $this->logOf($opportunity)->outcome,
            'Envelope e carimbo saem do mesmo desfecho: discordar é o defeito que a 9770 criou.'
        );
    }

    function testARefusalIsStoredWithTheStatusThatCameBack()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(422, ['detail' => 'ente_federado obrigatório']);

        $this->executeSend($opportunity->id);

        $log = $this->logOf($opportunity);
        $this->assertSame(SendOutcome::REJECTED, $log->outcome);
        $this->assertSame(422, $this->attemptsOf($log)[0]->httpStatus);
    }

    function testAnUnavailableApiLeavesTheEnvelopeOpenForTheRetry()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(500, 'Internal Server Error');

        $this->executeSend($opportunity->id, attempt: 1);

        $log = $this->logOf($opportunity);
        $this->assertSame(SendOutcome::PENDING, $log->outcome, 'Enquanto há tentativa pela frente, o envio não terminou.');
        $this->assertCount(1, $this->attemptsOf($log));
        $this->assertSame($log->uuid, $this->enqueuedSendJobs()[0]->logUuid, 'A retentativa precisa voltar ao mesmo envelope.');
    }

    function testTheRetryWritesIntoTheEnvelopeOfTheFirstAttempt()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(500, 'Internal Server Error');
        $this->executeSend($opportunity->id, attempt: 1);
        $primeiro = $this->logOf($opportunity);

        Plugin::instance()->transport = FakeTransport::replying(200, ['id_par_edital' => 53781]);
        $this->executeSend($opportunity->id, attempt: 2, logUuid: $primeiro->uuid);

        $this->assertSame(1, $this->storedLogCountOf($opportunity), 'Três tentativas pertencem a um envio só.');
        $log = $this->logOf($opportunity);
        $this->assertSame(SendOutcome::SUCCESS, $log->outcome);
        $this->assertSame([1, 2], array_map(fn ($tentativa) => $tentativa->attempt, $this->attemptsOf($log)));
    }

    function testExhaustingTheAttemptsClosesTheEnvelopeAsError()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::unreachable();

        $this->executeSend($opportunity->id, attempt: Plugin::instance()->sendMaxAttempts());

        $log = $this->logOf($opportunity);
        $this->assertSame(SendOutcome::ERROR, $log->outcome);
        $this->assertNull($this->attemptsOf($log)[0]->httpStatus, 'Não respondeu não tem status.');
    }

    // o campo sai do payload depois da elegibilidade: é assim que a falha anterior à requisição acontece de verdade
    function testAnIncompletePayloadBecomesAnAttemptWithoutRequest()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = $transporte = FakeTransport::replying(200);
        $this->removeFromPayload('numero_previsto_vagas');

        $this->executeSend($opportunity->id);

        $log = $this->logOf($opportunity);
        $this->assertSame([], $transporte->requestedUrls, 'Premissa do teste: o payload incompleto não chega a ser enviado.');
        $this->assertSame(SendOutcome::ERROR, $log->outcome);
        $tentativa = $this->attemptsOf($log)[0];
        $this->assertNull($tentativa->httpStatus, 'Falha anterior à requisição é tentativa sem status.');
        $this->assertNotNull($tentativa->errorMessage);
        $this->assertStringContainsString('/api/v1/oportunidades/', $tentativa->endpoint, 'O destino continua nomeado, mesmo sem a requisição ter saído.');
    }

    function testASimulatedSendIsStoredAsSimulated()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->mode = Plugin::MODE_DEV;
        Plugin::instance()->transport = FakeTransport::unreachable();

        $this->executeSend($opportunity->id);

        $log = $this->logOf($opportunity);
        $this->assertSame(SendOutcome::SIMULATED, $log->outcome, 'Simulado exibido como sucesso é o defeito que o CultEditais tem.');
        $this->assertNull($this->attemptsOf($log)[0]->httpStatus);
    }

    // a tentativa existe mesmo quando a falha não veio da API: sem ela, o envio sumiria do histórico
    function testAnUnexpectedFailureDuringTheSendStillLeavesAnAttempt()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->opportunitySender = new class (Plugin::instance(), Plugin::instance()->sealedOpportunity()) extends OpportunitySender {
            public function send(Opportunity $opportunity, ?Log $log = null, int $attempt = 1): SendOutcome
            {
                throw new RuntimeException('estouro no envio');
            }
        };

        try {
            $this->executeSend($opportunity->id);
        } finally {
            Plugin::instance()->opportunitySender = null;
        }

        $log = $this->logOf($opportunity);
        $this->assertSame(SendOutcome::ERROR, $log->outcome);
        $tentativa = $this->attemptsOf($log)[0];
        $this->assertNull($tentativa->httpStatus);
        $this->assertNotNull($tentativa->errorMessage, 'O motivo público entra na tentativa; a mensagem da exceção fica no log.');
    }

    private bool $removingFromPayload = false;

    private function removeFromPayload(string $contractKey): void
    {
        $this->removingFromPayload = true;
        $this->app->hook('conectaente.opportunityPayload', function ($opportunity, &$payload) use ($contractKey) {
            if ($this->removingFromPayload) {
                unset($payload[$contractKey]);
            }
        });
    }

    // desligado por flag, não por clearHooks(): o clear() do core não invalida o cache de hooks
    protected function tearDown(): void
    {
        $this->removingFromPayload = false;

        parent::tearDown();
    }

    private function executeSend(int $opportunityId, int $attempt = 1, ?string $logUuid = null): void
    {
        $jobType = new SendOpportunityJob(SendOpportunityJob::SLUG);
        $job = new Job($jobType);
        $job->opportunityId = $opportunityId;
        $job->attempt = $attempt;
        $job->logUuid = $logUuid;

        $jobType->_execute($job);
    }

    private function liveSealedOpportunity(): Opportunity
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        Plugin::instance()->mode = Plugin::MODE_LIVE;
        $this->purgeSendJobs();

        return $opportunity;
    }

    private function logOf(Opportunity $opportunity): ?Log
    {
        $this->app->em->clear();
        $logs = $this->app->repo(Log::class)->findBy(['opportunityId' => $opportunity->id], ['id' => 'DESC']);

        return $logs[0] ?? null;
    }

    /** @return LogAttempt[] */
    private function attemptsOf(Log $log): array
    {
        return $this->app->repo(LogAttempt::class)->findBy(['log' => $log], ['id' => 'ASC']);
    }

    private function storedLogCountOf(Opportunity $opportunity): int
    {
        return (int) $this->app->em->getConnection()->fetchOne(
            'SELECT count(*) FROM conectaente_log WHERE opportunity_id = ?',
            [$opportunity->id]
        );
    }
}
