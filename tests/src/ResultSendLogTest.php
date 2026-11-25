<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\Log;
use ConectaEnte\Http\HttpExchange;
use ConectaEnte\Http\Transport\CurlTransport;
use ConectaEnte\Plugin;
use ConectaEnte\Services\SendLogger;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * O histórico do envio de resultado: a ação no envelope e a trava contra o segundo envio.
 */
class ResultSendLogTest extends TestCase
{
    use PublicationRequirementsFixtures;

    function testAnOpportunitySendIsRecordedAsOpportunity()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertSame(Log::ACTION_OPPORTUNITY, $this->logger()->startOrResume($this->createOpportunity())->action);
    }

    function testAResultSendIsNeverAbandonedByAnOpportunitySend()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $result = $this->logger()->openResultSend($opportunity);

        $this->logger()->startOrResume($opportunity);
        $this->logger()->startOrResume($opportunity);

        $this->assertSame(SendOutcome::PENDING, $this->reloadedLog($result)->outcome, 'Abandonar o envelope não interrompe o POST de resultado em voo.');
        $this->assertCount(1, $this->app->repo(Log::class)->findBy(['opportunityId' => $opportunity->id, 'action' => Log::ACTION_OPPORTUNITY, 'outcome' => SendOutcome::PENDING]));
    }

    function testASecondResultSendIsRefusedWhileTheFirstIsPending()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $first = $this->logger()->openResultSend($opportunity);
        $second = $this->logger()->openResultSend($opportunity);

        $this->assertNotNull($first);
        $this->assertNull($second, 'Dois POSTs publicariam dois resultados que não se excluem.');
    }

    function testAnExpiredResultSendClosesAsErrorAndFreesTheNext()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $stale = $this->logger()->openResultSend($opportunity);
        $this->app->em->getConnection()->update('conectaente_log', ['create_timestamp' => (new \DateTime('-11 minutes'))->format('Y-m-d H:i:s')], ['id' => $stale->id]);

        $next = $this->logger()->openResultSend($opportunity);

        $this->assertNotNull($next);
        $this->assertSame(SendOutcome::ERROR, $this->reloadedLog($stale)->outcome, 'Vencido não é abandonado: fecha sem desfecho conhecido e vai para a conciliação.');
    }

    function testTheJobClaimsTheEnvelopeOnlyOnce()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->openResultSend($this->createOpportunity());

        $this->assertTrue($this->logger()->claimResultSend($log));
        $this->assertFalse($this->logger()->claimResultSend($log));
    }

    function testThePostIsRefusedAfterTheLockExpires()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->openResultSend($this->createOpportunity());
        $this->logger()->claimResultSend($log);
        $this->app->em->getConnection()->update('conectaente_log', ['started_at' => (new \DateTime('-11 minutes'))->format('Y-m-d H:i:s')], ['id' => $log->id]);

        $this->assertFalse($this->logger()->confirmResultPost($this->reloadedLog($log)), 'O job atrasado não posta depois de o prazo vencer.');
    }

    function testThePostIsRefusedCloseToTheEndOfTheLock()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->startedSend('-9 minutes');

        $this->assertFalse($this->logger()->confirmResultPost($log), 'O POST ainda estaria em voo quando a leitura da aba fechasse o envio.');
        $this->assertFalse($this->reloadedLog($log)->posted);
    }

    function testThePostIsConfirmedWhileItCanFinishInsideTheLock()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertTrue($this->logger()->confirmResultPost($this->startedSend('-7 minutes')));
    }

    function testThePostAlwaysFinishesBeforeTheLockExpires()
    {
        $this->assertLessThan(SendLogger::RESULT_LOCK_SECONDS, SendLogger::RESULT_POST_DEADLINE_SECONDS + CurlTransport::QUEUED_TIMEOUT);
    }

    function testAConfirmedPostIsMarkedAsPosted()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->openResultSend($this->createOpportunity());
        $this->logger()->claimResultSend($log);

        $this->assertTrue($this->logger()->confirmResultPost($log));
        $this->assertTrue($this->reloadedLog($log)->posted);
    }

    function testACreatedResultIsASuccessOnlyForTheResult()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $result = $this->logger()->openResultSend($opportunity);
        $edital = $this->logger()->startOrResume($opportunity);
        $created = new HttpExchange(endpoint: 'https://cultbr.example/api/v1/oportunidades/1/resultado', status: 201, responseBody: '{"id_par_edital_resultado": 1}');

        $this->logger()->recordAttempts($result, [$created], SendOutcome::SUCCESS, 1, 1);
        $this->logger()->recordAttempts($edital, [$created], SendOutcome::SUCCESS, 1, 3);

        $this->assertSame(SendOutcome::SUCCESS, $this->attemptOf($result)->outcome);
        $this->assertSame(SendOutcome::REJECTED, $this->attemptOf($edital)->outcome, 'O edital só aceita 200.');
    }

    function testTheReceivedValueOfARefusedRowIsNotStored()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->openResultSend($this->createOpportunity());
        $refused = new HttpExchange(endpoint: 'https://cultbr.example/x', status: 422, responseBody: json_encode(['message' => 'Há linhas inválidas.', 'inconsistencias' => [['linha' => 2, 'coluna' => 'cpf', 'valor_recebido' => '52998224725']]]));

        $this->logger()->recordAttempts($log, [$refused], SendOutcome::REJECTED, 1, 1);

        $this->assertStringNotContainsString('52998224725', $this->attemptOf($log)->responseBody, 'O valor recebido pode ser o CPF de um contemplado.');
        $this->assertStringContainsString('cpf', $this->attemptOf($log)->responseBody);
    }

    function testTheResultContextIsKeptInTheEnvelope()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->openResultSend($this->createOpportunity());

        $this->logger()->keepResultContext($log, 20073, true, str_repeat('a', 64), 3, '2026-11-22T10:00:00-03:00');

        $reloaded = $this->reloadedLog($log);
        $this->assertSame(20073, $reloaded->seenResultId);
        $this->assertTrue($reloaded->seenResultRead);
        $this->assertSame(3, $reloaded->resultType);
    }

    function testTheHistoryCanBeFilteredByAction()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $this->logger()->startOrResume($opportunity);
        $this->logger()->openResultSend($opportunity);

        $results = Plugin::instance()->sendHistory()->of($opportunity, action: Log::ACTION_RESULT);

        $this->assertSame(1, $results['total']);
        $this->assertSame(Log::ACTION_RESULT, $results['sends'][0]['action']);
        $this->assertSame(2, Plugin::instance()->sendHistory()->of($opportunity)['total']);
    }

    private function startedSend(string $age): Log
    {
        $log = $this->logger()->openResultSend($this->createOpportunity());
        $this->logger()->claimResultSend($log);
        $this->app->em->getConnection()->update('conectaente_log', ['started_at' => (new \DateTime($age))->format('Y-m-d H:i:s')], ['id' => $log->id]);

        return $this->reloadedLog($log);
    }

    private function logger(): SendLogger
    {
        return Plugin::instance()->sendLogger();
    }

    private function reloadedLog(Log $log): Log
    {
        $this->app->em->clear();

        return $this->app->repo(Log::class)->find($log->id);
    }

    private function attemptOf(Log $log): \ConectaEnte\Entities\LogAttempt
    {
        return $this->app->repo(\ConectaEnte\Entities\LogAttempt::class)->findOneBy(['log' => $log->id], ['id' => 'DESC']);
    }
}
