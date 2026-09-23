<?php

namespace Tests\ConectaEnte\Jobs;

use ConectaEnte\Http\ParInformationResult;
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

    private function run(): void
    {
        $jobType = new ParInformationSyncJob(ParInformationSyncJob::SLUG);
        $jobType->_execute(new Job($jobType));
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
}
