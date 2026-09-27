<?php

namespace Tests\ConectaEnte\Jobs;

use ConectaEnte\Http\ParInformationResult;
use ConectaEnte\Http\Transport\TransportInterface;
use ConectaEnte\Jobs\ParInformationSyncJob;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ParInformationService;
use MapasCulturais\App;
use MapasCulturais\Entities\Job;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;

class ParInformationSyncJobTest extends TestCase
{
    use ConectaEnteFixtures;

    function setUp(): void
    {
        parent::setUp();
        Plugin::instance()->transport = null;
    }

    private function run(): bool
    {
        $jobType = new ParInformationSyncJob(ParInformationSyncJob::SLUG);

        return $jobType->_execute(new Job($jobType));
    }

    private function cachedResult(\ConectaEnte\Entities\FederativeEntity $federativeEntity): ?ParInformationResult
    {
        $key = ParInformationService::cacheKey($federativeEntity);

        return App::i()->cache->contains($key) ? App::i()->cache->fetch($key) : null;
    }

    function testPopulatesTheCacheForEachActiveFederativeEntityMatchedByCnpj()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal, document: '12345678000190');

        Plugin::instance()->transport = FakeTransport::replying(200, ['pagination' => [], 'data' => [
            ['cnpj' => '12345678000190', 'exercicios' => [['id' => '1']]],
        ]]);

        $this->run();

        $result = $this->cachedResult($federativeEntity);

        $this->assertNotNull($result);
        $this->assertNotNull($result->tree);
        $this->assertSame('1', $result->tree->exercicios[0]->id);
    }

    function testDoesNotOverwriteAGoodCacheOnTransportFailure()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal, document: '12345678000190');

        $this->primeParInformationCache($federativeEntity, [['id' => 'ja-em-cache']]);

        Plugin::instance()->transport = FakeTransport::unreachable();

        $this->run();

        $result = $this->cachedResult($federativeEntity);

        $this->assertNotNull($result);
        $this->assertSame('ja-em-cache', $result->tree->exercicios[0]->id);
    }

    function testTrashedFederativeEntitiesAreSkipped()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal, document: '12345678000190');

        $this->app->disableAccessControl();
        $federativeEntity->delete(true);
        $this->app->enableAccessControl();

        Plugin::instance()->transport = FakeTransport::replying(200, ['pagination' => [], 'data' => [
            ['cnpj' => '12345678000190', 'exercicios' => [['id' => '1']]],
        ]]);

        $this->run();

        $this->assertCount(0, Plugin::instance()->transport->requestedUrls);
    }

    /**
     * CodeRabbit: uma exceção não tratada deixaria o job preso em PROCESSING
     * para sempre (ITERATIONS grande não passa pela limpeza de job travado).
     */
    function testAlwaysReturnsTrueEvenWhenAnEntitySyncThrows()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal, document: '12345678000190');

        Plugin::instance()->transport = new class implements TransportInterface {
            public function get(string $url, array $headers = []): \ConectaEnte\Http\Response
            {
                throw new \RuntimeException('falha simulada de transporte');
            }
        };

        $this->assertTrue($this->run());
    }

    function testAFailingEntityDoesNotStopSyncingTheOthers()
    {
        $this->loginAsSaasSuperAdmin();
        $seal1 = $this->createSeal();
        $seal2 = $this->createSealWithValidity(0);
        $entityThatFails = $this->createFederativeEntityWithSeal($seal1, name: 'Ente que falha', document: '12345678000190');
        $entityOk = $this->createFederativeEntityWithSeal($seal2, name: 'Ente que funciona', document: '98765432000100');

        Plugin::instance()->transport = new class ($entityThatFails->token) implements TransportInterface {
            public function __construct(private string $tokenThatFails)
            {
            }

            public function get(string $url, array $headers = []): \ConectaEnte\Http\Response
            {
                if (($headers['token'] ?? null) === $this->tokenThatFails) {
                    throw new \RuntimeException('falha simulada de transporte');
                }

                return \ConectaEnte\Http\Response::received(200, json_encode(['pagination' => [], 'data' => [
                    ['cnpj' => '98765432000100', 'exercicios' => [['id' => '1']]],
                ]]));
            }
        };

        $this->run();

        $result = $this->cachedResult($entityOk);
        $this->assertNotNull($result);
        $this->assertSame('1', $result->tree->exercicios[0]->id);
        $this->assertNull($this->cachedResult($entityThatFails));
    }
}
