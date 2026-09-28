<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use ConectaEnte\Services\ParInformationService;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * O serviço só lê o cache; quem fala com a API é o job de sincronização.
 */
class ParInformationServiceTest extends TestCase
{
    use PublicationRequirementsFixtures;

    function testOpportunityWithoutASealGivesNull()
    {
        $opportunity = $this->coreCompleteOpportunity(Opportunity::STATUS_DRAFT);

        $this->assertNull(Plugin::instance()->parInformationService()->getForOpportunity($opportunity));
    }

    function testReadsTheTreeTheJobLeftInTheCache()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $federativeEntity = $this->resolveFederativeEntity($opportunity);
        $this->primeParInformationCache($federativeEntity, [['id' => '2024', 'ano' => '2024']]);

        $result = Plugin::instance()->parInformationService()->getForOpportunity($opportunity);

        $this->assertSame('2024', $result->tree->exercises[0]->year);
    }

    function testEmptyCacheIsUnavailableWithoutTouchingTheApi()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, ['data' => []]);

        $result = Plugin::instance()->parInformationService()->getForOpportunity($opportunity);

        $this->assertTrue($result->unavailable);
        $this->assertSame([], $transport->requestedUrls, 'A requisição do usuário nunca espera a API.');
    }

    function testGarbageInTheCacheIsUnavailableInsteadOfThrowing()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $federativeEntity = $this->resolveFederativeEntity($opportunity);
        $this->app->mscache->save(ParInformationService::cacheKey($federativeEntity), 'lixo', 3600);

        $result = Plugin::instance()->parInformationService()->getForOpportunity($opportunity);

        $this->assertTrue($result->unavailable);
    }

    function testCachedNotFoundIsPassedThrough()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $this->primeParInformationNotFound($this->resolveFederativeEntity($opportunity));

        $result = Plugin::instance()->parInformationService()->getForOpportunity($opportunity);

        $this->assertTrue($result->notFound);
        $this->assertFalse($result->unavailable);
    }

    function testCacheSurvivesTheSubsiteNamespaceSwitch()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $federativeEntity = $this->resolveFederativeEntity($opportunity);
        $this->primeParInformationCache($federativeEntity, [['id' => '2024']]);

        // é o que o core faz ao servir uma requisição de subsite (App::_initTheme)
        $namespace = new \ReflectionProperty($this->app->cache, 'namespace');
        $original = $namespace->getValue($this->app->cache);
        $this->app->cache->setNamespace('teste:999');

        try {
            $result = Plugin::instance()->parInformationService()->getForOpportunity($opportunity);
        } finally {
            $namespace->setValue($this->app->cache, $original);
        }

        $this->assertNotNull($result->tree, 'O job grava sem subsite; a tela lê de dentro de um — o dado precisa atravessar.');
    }

    function testTtlComesFromTheSyncInterval()
    {
        $service = Plugin::instance()->parInformationService();

        $this->assertSame(
            Plugin::DEFAULT_PAR_SYNC_INTERVAL_MINUTES * 3 * 60,
            $service->cacheTtl(),
            'Três intervalos: a árvore sobrevive a duas sincronizações perdidas.',
        );
    }
}
