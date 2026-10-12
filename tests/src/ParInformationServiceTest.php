<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use ConectaEnte\Services\ParInformationService;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * O serviço serve o cache enquanto ele está fresco e busca na API quando não está.
 */
class ParInformationServiceTest extends TestCase
{
    use PublicationRequirementsFixtures;

    protected function tearDown(): void
    {
        Plugin::instance()->transport = null;

        parent::tearDown();
    }

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

    function testEmptyCacheFetchesFromTheApiAndKeepsIt()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $federativeEntity = $this->resolveFederativeEntity($opportunity);
        $this->app->mscache->delete(ParInformationService::cacheKey($federativeEntity));
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, ['data' => [
            ['cnpj' => $federativeEntity->document, 'exercicios' => [['id' => '2026', 'ano' => '2026']]],
        ]]);

        $result = Plugin::instance()->parInformationService()->getForOpportunity($opportunity);

        $this->assertSame('2026', $result->tree->exercises[0]->year, 'Cache vazio não trava a tela: busca na hora.');
        $this->assertCount(1, $transport->requestedUrls);
        $this->assertInstanceOf(
            \ConectaEnte\Http\ParInformationResult::class,
            $this->app->mscache->fetch(ParInformationService::cacheKey($federativeEntity)),
            'O que foi buscado fica no cache, para a próxima abertura não pagar de novo.',
        );
    }

    function testWarmCacheIsServedWithoutTouchingTheApi()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $this->primeParInformationCache($this->resolveFederativeEntity($opportunity), [['id' => '2024', 'ano' => '2024']]);
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, ['data' => []]);

        $result = Plugin::instance()->parInformationService()->getForOpportunity($opportunity);

        $this->assertSame('2024', $result->tree->exercises[0]->year);
        $this->assertSame([], $transport->requestedUrls, 'Dentro da validade, a árvore sai do cache.');
    }

    function testApiFailureLeavesNoTreeAndIsNotCached()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $federativeEntity = $this->resolveFederativeEntity($opportunity);
        $this->app->mscache->delete(ParInformationService::cacheKey($federativeEntity));
        Plugin::instance()->transport = FakeTransport::unreachable();

        $result = Plugin::instance()->parInformationService()->getForOpportunity($opportunity);

        $this->assertNull($result->tree);
        $this->assertTrue($result->unreachable, 'O motivo precisa sobreviver: é ele que a tela mostra ao gestor.');
        $this->assertFalse($this->app->mscache->contains(ParInformationService::cacheKey($federativeEntity)), 'CultBR fora do ar não vira "ente sem PAR" por cinco minutos.');
    }

    function testGarbageInTheCacheIsIgnoredInsteadOfThrowing()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $federativeEntity = $this->resolveFederativeEntity($opportunity);
        $this->app->mscache->save(ParInformationService::cacheKey($federativeEntity), 'lixo', 3600);
        Plugin::instance()->transport = FakeTransport::unreachable();

        $result = Plugin::instance()->parInformationService()->getForOpportunity($opportunity);

        $this->assertNull($result->tree, 'Classe antiga no cache não pode explodir nem virar árvore.');
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

    function testTtlIsShortBecauseTheCultBrChangesThePar()
    {
        $this->assertSame(
            Plugin::DEFAULT_PAR_CACHE_TTL_MINUTES * 60,
            Plugin::instance()->parInformationService()->cacheTtl(),
            'Cinco minutos: árvore vencida faz o gestor escolher atividade que já não existe.',
        );
    }
}
