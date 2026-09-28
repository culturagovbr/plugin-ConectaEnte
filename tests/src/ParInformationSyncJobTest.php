<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Response;
use ConectaEnte\Jobs\ParInformationSyncJob;
use ConectaEnte\Plugin;
use MapasCulturais\App;
use MapasCulturais\Entities\Job;
use Monolog\Handler\TestHandler;
use RuntimeException;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Doubles\QueueTransport;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;

/**
 * O job que leva a árvore do PAR da API ao cache, ente a ente.
 */
class ParInformationSyncJobTest extends TestCase
{
    use ConectaEnteFixtures;

    function testPopulatesTheCacheOfEachEnabledEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $first = $this->createFederativeEntity('Município A', '12200176000176');
        $second = $this->createFederativeEntity('Município B', '82951229000176');
        Plugin::instance()->transport = FakeTransport::replying(200, ['data' => [
            ['cnpj' => '12200176000176', 'exercicios' => [['id' => 'a-2024']]],
            ['cnpj' => '82951229000176', 'exercicios' => [['id' => 'b-2024']]],
        ]]);

        $this->executeJob();

        $service = Plugin::instance()->parInformationService();
        $this->assertSame('a-2024', $service->cachedForFederativeEntity($first)->tree->exercises[0]->id);
        $this->assertSame('b-2024', $service->cachedForFederativeEntity($second)->tree->exercises[0]->id);
    }

    function testWritesNotFoundToTheCache()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        Plugin::instance()->transport = FakeTransport::replying(404, ['detail' => 'Not Found']);

        $this->executeJob();

        $result = Plugin::instance()->parInformationService()->cachedForFederativeEntity($federativeEntity);

        $this->assertTrue($result->notFound, 'O ambiente sem a rota fica registrado, para a tela avisar sem esperar a API.');
    }

    function testTransportFailureDoesNotOverwriteAGoodCache()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        $this->primeParInformationCache($federativeEntity, [['id' => '2024']]);
        Plugin::instance()->transport = FakeTransport::unreachable();

        $this->executeJob();

        $result = Plugin::instance()->parInformationService()->cachedForFederativeEntity($federativeEntity);

        $this->assertNotNull($result->tree, 'A árvore boa fica até a API voltar.');
    }

    function testRejectedTokenIsNotCachedAndIsLogged()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        Plugin::instance()->transport = FakeTransport::replying(401, ['detail' => 'Token inválido']);
        $handler = $this->captureLog();

        $this->executeJob();

        $result = Plugin::instance()->parInformationService()->cachedForFederativeEntity($federativeEntity);

        $this->assertNull($result, 'Token rejeitado não pode virar "ente sem PAR" no cache.');
        $this->assertTrue(
            $handler->hasWarningThatContains("falha ao atualizar o ente {$federativeEntity->id}: Token inválido"),
            'Sem o motivo, o log não distingue token rejeitado de CultBR fora do ar.',
        );
    }

    function testTrashedEntityIsSkipped()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntity()->delete(true);
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, ['data' => []]);

        $this->executeJob();

        $this->assertSame([], $transport->requestedUrls, 'Ente na lixeira não gasta chamada.');
    }

    function testAFailingEntityDoesNotStopTheOthers()
    {
        $this->loginAsSaasSuperAdmin();
        $first = $this->createFederativeEntity('Município A', '12200176000176');
        $second = $this->createFederativeEntity('Município B', '82951229000176');
        Plugin::instance()->transport = QueueTransport::replying(
            new RuntimeException('estouro no primeiro ente'),
            Response::received(200, json_encode(['data' => [['cnpj' => '82951229000176', 'exercicios' => [['id' => 'b-2024']]]]])),
        );
        $handler = $this->captureLog();

        $this->executeJob();

        $service = Plugin::instance()->parInformationService();
        $this->assertNull($service->cachedForFederativeEntity($first), 'Ente que estourou não deixa nada no cache.');
        $this->assertSame('b-2024', $service->cachedForFederativeEntity($second)->tree->exercises[0]->id);
        $this->assertTrue($handler->hasErrorThatContains("exceção ao atualizar o ente {$first->id}"));
    }

    function testEnqueueIsIdempotentThanksToTheDeterministicId()
    {
        $this->purgeScheduledSync();

        Plugin::instance()->scheduleParSync();
        Plugin::instance()->scheduleParSync();

        $jobs = $this->app->repo(Job::class)->findBy(['id' => $this->scheduledSyncId()]);

        $this->assertCount(1, $jobs);
        $this->assertSame(1, $jobs[0]->iterations, 'Uma iteração por agendamento: é o que deixa a limpeza de job preso do core funcionar.');
        $this->assertGreaterThan(new \DateTime('+1 minute'), $jobs[0]->nextExecutionTimestamp, 'O agendado de rotina espera o intervalo; imediato é só o do ente salvo.');
    }

    function testSavingAFederativeEntitySchedulesAnImmediateSync()
    {
        $this->loginAsSaasSuperAdmin();
        $this->purgeScheduledSync();

        $this->createFederativeEntity();

        $job = $this->app->repo(Job::class)->findOneBy(['id' => $this->scheduledSyncId()]);

        $this->assertNotNull($job);
        $this->assertLessThanOrEqual(new \DateTime(), $job->nextExecutionTimestamp, 'Token novo não espera o próximo ciclo.');
    }

    function testSavingAnEntityDuringARunningSyncDoesNotDisturbIt()
    {
        $this->loginAsSaasSuperAdmin();
        $this->purgeScheduledSync();
        Plugin::instance()->scheduleParSync();
        $running = $this->app->repo(Job::class)->findOneBy(['id' => $this->scheduledSyncId()]);
        $running->status = Job::STATUS_PROCESSING;
        $running->save(true);

        $this->createFederativeEntity();

        $after = $this->app->repo(Job::class)->findOneBy(['id' => $this->scheduledSyncId()]);

        $this->assertSame($running->pk, $after->pk, 'Substituir o job em execução derrubaria o worker que o carrega.');
        $this->assertSame(Job::STATUS_PROCESSING, $after->status);
    }

    private function scheduledSyncId(): string
    {
        return md5(ParInformationSyncJob::SLUG . ':' . ParInformationSyncJob::SLUG);
    }

    // o dump de testes carrega o agendamento antigo; cada cenário parte do zero
    private function purgeScheduledSync(): void
    {
        $this->app->em->getConnection()->delete('job', ['id' => $this->scheduledSyncId()]);
    }

    private function executeJob(): void
    {
        $jobType = new ParInformationSyncJob(ParInformationSyncJob::SLUG);
        $jobType->_execute(new Job($jobType));
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
}
