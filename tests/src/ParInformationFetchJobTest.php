<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Jobs\ParInformationFetchJob;
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
 * O job que leva a árvore do PAR de um Ente Federado da API ao cache.
 */
class ParInformationFetchJobTest extends TestCase
{
    use ConectaEnteFixtures;

    function testPopulatesTheCacheOfTheEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity('Município A', '12200176000176');
        Plugin::instance()->transport = FakeTransport::replying(200, ['data' => [
            ['cnpj' => '12200176000176', 'exercicios' => [['id' => 'a-2024']]],
        ]]);

        $this->executeFetch($federativeEntity->id);

        $result = Plugin::instance()->parInformationService()->cachedForFederativeEntity($federativeEntity);

        $this->assertSame('a-2024', $result->tree->exercises[0]->id);
    }

    function testWritesNotFoundToTheCache()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        Plugin::instance()->transport = FakeTransport::replying(404, ['detail' => 'Not Found']);

        $this->executeFetch($federativeEntity->id);

        $result = Plugin::instance()->parInformationService()->cachedForFederativeEntity($federativeEntity);

        $this->assertTrue($result->notFound, 'O ambiente sem a rota fica registrado, para a tela avisar sem esperar a API.');
    }

    function testTransportFailureDoesNotOverwriteAGoodCache()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        $this->primeParInformationCache($federativeEntity, [['id' => '2024']]);
        Plugin::instance()->transport = FakeTransport::unreachable();

        $this->executeFetch($federativeEntity->id);

        $result = Plugin::instance()->parInformationService()->cachedForFederativeEntity($federativeEntity);

        $this->assertNotNull($result->tree, 'A árvore boa fica até a API voltar.');
    }

    function testRejectedTokenIsNotCachedAndIsLogged()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        Plugin::instance()->transport = FakeTransport::replying(401, ['detail' => 'Token inválido']);
        $handler = $this->captureLog();

        $this->executeFetch($federativeEntity->id);

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
        $federativeEntity = $this->createFederativeEntity();
        $federativeEntity->delete(true);
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, ['data' => []]);

        $this->executeFetch($federativeEntity->id);

        $this->assertSame([], $transport->requestedUrls, 'Ente que foi para a lixeira entre a seleção e a execução não gasta chamada.');
    }

    function testAnEntityThatNoLongerExistsIsSkippedWithoutNoise()
    {
        $this->loginAsSaasSuperAdmin();
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, ['data' => []]);
        $handler = $this->captureLog();

        $this->executeFetch($this->vanishedEntityId());

        $this->assertSame([], $transport->requestedUrls);
        $this->assertFalse($handler->hasErrorRecords(), 'Ente apagado entre a seleção e a execução é rotina, não incidente para investigar no log.');
    }

    function testAnEntityThatBlowsUpIsLoggedAndTheJobStillEnds()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        Plugin::instance()->transport = QueueTransport::replying(new RuntimeException('estouro no ente'));
        $handler = $this->captureLog();

        $finished = $this->executeFetch($federativeEntity->id);

        $this->assertTrue($finished, 'Job que não termina fica preso em processamento e o ente perde o próximo ciclo.');
        $this->assertTrue($handler->hasErrorThatContains("exceção ao atualizar o ente {$federativeEntity->id}"));
    }

    function testEachEntityHasItsOwnJobId()
    {
        $this->loginAsSaasSuperAdmin();
        $first = $this->createFederativeEntity('Município A', '12200176000176');
        $second = $this->createFederativeEntity('Município B', '82951229000176');
        $jobType = new ParInformationFetchJob(ParInformationFetchJob::SLUG);

        $this->assertNotSame(
            $jobType->generateId(ParInformationFetchJob::dataFor($first), 'now', '', 1),
            $jobType->generateId(ParInformationFetchJob::dataFor($second), 'now', '', 1),
            'Com um id só, o ente enfileirado depois substituiria o anterior.',
        );
    }

    private function vanishedEntityId(): int
    {
        $last = $this->app->repo(FederativeEntity::class)->findBy([], ['id' => 'DESC'], 1);

        return $last ? $last[0]->id + 1 : 1;
    }

    private function executeFetch(int $federativeEntityId): bool
    {
        $jobType = new ParInformationFetchJob(ParInformationFetchJob::SLUG);
        $job = new Job($jobType);
        $job->federativeEntityId = $federativeEntityId;

        return $jobType->_execute($job);
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
