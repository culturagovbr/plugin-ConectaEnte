<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Dto\ParInformation;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ParInformationService;
use MapasCulturais\App;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;

class ParInformationServiceTest extends TestCase
{
    use ConectaEnteFixtures;

    private array $exercicios = [
        ['id' => '1', 'nome' => '2024', 'metas' => [
            ['id' => '10', 'nome' => 'Meta', 'acoes' => [
                ['id' => '100', 'nome' => 'Ação', 'atividades' => [
                    ['id' => '1000', 'nome' => 'Atividade'],
                ]],
            ]],
        ]],
    ];

    function setUp(): void
    {
        parent::setUp();
        Plugin::instance()->transport = null;
    }

    function testOpportunityWithoutSealResolvesNoResult()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $this->assertNull((new ParInformationService)->getForOpportunity($opportunity));
    }

    /**
     * A requisição nunca chama a API: só lê o cache já populado pelo job.
     */
    function testOpportunityWithSealReadsTheLinkedEntitysTreeFromCache()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        $this->primeParInformationCache($federativeEntity, $this->exercicios);

        $result = (new ParInformationService)->getForOpportunity($opportunity);

        $this->assertNotNull($result);
        $this->assertNotNull($result->tree);
        $this->assertSame('1', $result->tree->exercicios[0]->id);
    }

    function testCacheMissNeverCallsTheApiAndIsReportedAsUnavailable()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);

        // sem transporte configurado: qualquer chamada real quebraria o teste
        Plugin::instance()->transport = FakeTransport::unreachable();

        $result = (new ParInformationService)->getForFederativeEntity($federativeEntity);

        $this->assertTrue($result->unavailable);
        $this->assertNull($result->tree);
    }

    /**
     * CodeRabbit: um valor de outro tipo no cache (ex. classe antiga sobrevivendo de um
     * deploy anterior) não pode explodir com TypeError — vira "indisponível".
     */
    function testGarbageInTheCacheIsReportedAsUnavailableInsteadOfThrowing()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);

        App::i()->cache->save(ParInformationService::cacheKey($federativeEntity), 'lixo-de-outra-versao', 3600);

        $result = (new ParInformationService)->getForFederativeEntity($federativeEntity);

        $this->assertTrue($result->unavailable);
    }

    function testConsistentPathIsAccepted()
    {
        $tree = ParInformation::fromApiListResponse([
            'data' => [['cnpj' => '12345678000190', 'exercicios' => $this->exercicios]],
        ], '12345678000190');

        $this->assertTrue($tree->isConsistentPath('1', '10', '100', '1000'));
    }

    function testInconsistentPathIsRejected()
    {
        $tree = ParInformation::fromApiListResponse([
            'data' => [['cnpj' => '12345678000190', 'exercicios' => $this->exercicios]],
        ], '12345678000190');

        $this->assertFalse($tree->isConsistentPath('1', '10', '100', '9999'));
        $this->assertFalse($tree->isConsistentPath('1', '99', '100', '1000'));
    }
}
