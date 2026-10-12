<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\Log;
use ConectaEnte\Entities\LogAttempt;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Exceptions\PermissionDenied;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;

class LogTest extends TestCase
{
    use ConectaEnteFixtures;

    private const ACTION_OUTSIDE_THE_LIST = 'archive';

    function testLogKeepsItsAttemptsInOrder()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->createLog();
        $this->createAttempt($log, attempt: 1, httpStatus: 500);
        $this->createAttempt($log, attempt: 2, httpStatus: 201, outcome: SendOutcome::SUCCESS);

        $stored = $this->reloadLog($log);

        $this->assertCount(2, $stored->attempts);
        $this->assertSame([1, 2], array_map(fn ($attempt) => $attempt->attempt, $stored->attempts->toArray()));
    }

    function testLogKeepsWhoTriggeredItAndWhichEditalItProduced()
    {
        $user = $this->userDirector->createUser(['saasSuperAdmin']);
        $this->login($user);
        $log = $this->createLog(parEditalId: 53781);

        $stored = $this->reloadLog($log);

        $this->assertSame($user->id, $stored->triggeredByUser->id);
        $this->assertSame(53781, $stored->parEditalId);
    }

    function testAttemptWithoutRequestKeepsTheStatusNull()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->createLog();
        $attempt = $this->createAttempt($log, httpStatus: null, outcome: SendOutcome::ERROR);

        $this->app->em->clear();
        $stored = $this->app->repo(LogAttempt::class)->find($attempt->id);

        $this->assertNull($stored->httpStatus);
        $this->assertSame(SendOutcome::ERROR, $stored->outcome);
    }

    function testTrashingTheOpportunityKeepsItsLogs()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $log = $this->createLog($opportunity->id);

        $opportunity->delete(true);

        $this->assertSame(Opportunity::STATUS_TRASH, $opportunity->status);
        $this->assertNotNull($this->reloadLog($log));
    }

    function testRemovingTheOpportunityRowKeepsItsLogs()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $log = $this->createLog($opportunity->id);
        $opportunityId = $opportunity->id;

        $this->app->em->clear();
        $this->app->em->getConnection()->executeStatement('DELETE FROM opportunity WHERE id = ?', [$opportunityId]);

        $stored = $this->reloadLog($log);

        $this->assertNotNull($stored);
        $this->assertSame($opportunityId, $stored->opportunityId);
    }

    function testNothingConstrainsTheOpportunityOfALog()
    {
        $connection = $this->app->em->getConnection();

        $column = $connection->fetchOne(
            "SELECT column_name FROM information_schema.columns
             WHERE table_name = 'conectaente_log' AND column_name = 'opportunity_id'"
        );

        $constraints = $connection->fetchFirstColumn(
            "SELECT constraint_name FROM information_schema.key_column_usage
             WHERE table_name = 'conectaente_log' AND column_name = 'opportunity_id'"
        );

        $this->assertSame('opportunity_id', $column);
        $this->assertSame([], $constraints);
    }

    function testDeletingTheLogRemovesItsAttempts()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->createLog();
        $this->createAttempt($log);

        $this->app->em->getConnection()->executeStatement('DELETE FROM conectaente_log WHERE id = ?', [$log->id]);

        $this->assertSame(0, $this->storedAttemptCountOf($log));
    }

    function testTheAuthorForeignKeyIsDeclaredAsSetNull()
    {
        $definition = $this->app->em->getConnection()->fetchOne(
            "SELECT pg_get_constraintdef(c.oid) FROM pg_constraint c
             JOIN pg_class t ON t.oid = c.conrelid
             WHERE t.relname = 'conectaente_log' AND c.conname = 'fk_conectaente_log_user'"
        );

        $this->assertStringContainsString('ON DELETE SET NULL', $definition);
    }

    function testTheUuidColumnHasAUniqueIndex()
    {
        $definition = $this->indexDefinitionOf('unq_conectaente_log_uuid');

        $this->assertStringContainsString('UNIQUE INDEX', $definition);
        $this->assertStringContainsString('(uuid)', $definition);
    }

    function testLogsAreIndexedForListingByOpportunity()
    {
        $definition = $this->indexDefinitionOf('idx_conectaente_log_opportunity');

        $this->assertStringContainsString('(opportunity_id, create_timestamp DESC)', $definition);
    }

    function testAttemptsAreIndexedInInsertionOrder()
    {
        $definition = $this->indexDefinitionOf('idx_conectaente_log_attempt_log');

        $this->assertStringContainsString('(log_id, id)', $definition);
    }

    function testNoUserReachesALogThroughTheEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->createLog();

        $this->assertEveryActionIsDenied($log);

        $this->login($this->userDirector->createUser());

        $this->assertEveryActionIsDenied($log);
    }

    function testNoUserReachesAnAttemptThroughTheEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $attempt = $this->createAttempt($this->createLog());

        $this->assertEveryActionIsDenied($attempt);

        $this->login($this->userDirector->createUser());

        $this->assertEveryActionIsDenied($attempt);
    }

    function testSavingANewLogIsDenied()
    {
        $this->loginAsSaasSuperAdmin();

        $log = new Log;
        $log->uuid = 'c0ffee00-0000-4000-8000-000000000000';
        $log->opportunityId = 1;
        $log->outcome = SendOutcome::SUCCESS;

        $this->expectException(PermissionDenied::class);

        $log->save(true);
    }

    function testSavingAStoredLogIsDenied()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->createLog();

        $log->outcome = SendOutcome::ERROR;

        $this->expectException(PermissionDenied::class);

        $log->save(true);
    }

    function testDeletingALogIsDenied()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->createLog();

        $this->expectException(PermissionDenied::class);

        $log->delete(true);
    }

    private function createLog(?int $opportunityId = null, ?int $parEditalId = null): Log
    {
        $log = new Log;
        $log->uuid = uniqid('uuid-');
        $log->opportunityId = $opportunityId ?? 1;
        $log->parEditalId = $parEditalId;
        $log->outcome = SendOutcome::SUCCESS;
        $log->triggeredByUser = $this->app->user;

        $this->app->em->persist($log);
        $this->app->em->flush();

        return $log;
    }

    private function createAttempt(
        Log $log,
        int $attempt = 1,
        ?int $httpStatus = 201,
        string $outcome = SendOutcome::SUCCESS,
    ): LogAttempt {
        $logAttempt = new LogAttempt;
        $logAttempt->log = $log;
        $logAttempt->attempt = $attempt;
        $logAttempt->maxAttempts = 3;
        $logAttempt->endpoint = 'https://cultbr.example/api/edital';
        $logAttempt->httpMethod = 'POST';
        $logAttempt->httpStatus = $httpStatus;
        $logAttempt->outcome = $outcome;
        $logAttempt->sentAt = new \DateTime;

        $this->app->em->persist($logAttempt);
        $this->app->em->flush();

        return $logAttempt;
    }

    private function reloadLog(Log $log): ?Log
    {
        $id = $log->id;
        $this->app->em->clear();

        return $this->app->repo(Log::class)->find($id);
    }

    private function storedAttemptCountOf(Log $log): int
    {
        return (int) $this->app->em->getConnection()->fetchOne(
            'SELECT count(*) FROM conectaente_log_attempt WHERE log_id = ?',
            [$log->id]
        );
    }

    private function assertEveryActionIsDenied(Log|LogAttempt $entity): void
    {
        $actions = array_merge($entity::getPermissionsList(), [self::ACTION_OUTSIDE_THE_LIST]);

        foreach ($actions as $action) {
            $this->assertFalse($entity->canUser($action), $action);
        }
    }

    private function indexDefinitionOf(string $indexName): string
    {
        return (string) $this->app->em->getConnection()->fetchOne(
            'SELECT indexdef FROM pg_indexes WHERE indexname = ?',
            [$indexName]
        );
    }
}
