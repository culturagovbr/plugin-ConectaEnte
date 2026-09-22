<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use ConectaEnte\Services\ParInformationService;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;

class ParInformationServiceTest extends TestCase
{
    use ConectaEnteFixtures;

    private array $treeBody = ['exercicios' => [
        ['id' => '1', 'nome' => '2024', 'metas' => [
            ['id' => '10', 'nome' => 'Meta', 'acoes' => [
                ['id' => '100', 'nome' => 'Ação', 'atividades' => [
                    ['id' => '1000', 'nome' => 'Atividade'],
                ]],
            ]],
        ]],
    ]];

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

    function testOpportunityWithSealFetchesTheLinkedEntitysTree()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        Plugin::instance()->transport = FakeTransport::replying(200, $this->treeBody);

        $result = (new ParInformationService)->getForOpportunity($opportunity);

        $this->assertNotNull($result);
        $this->assertNotNull($result->tree);
        $this->assertSame('1', $result->tree->exercicios[0]->id);
    }

    function testFetchIsCachedPerFederativeEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        $transport = FakeTransport::replying(200, $this->treeBody);
        Plugin::instance()->transport = $transport;

        $service = new ParInformationService;
        $service->getForFederativeEntity($federativeEntity);
        $service->getForFederativeEntity($federativeEntity);

        $this->assertCount(1, $transport->requestedUrls);
    }

    function testUnreachableResultIsNotCached()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);

        Plugin::instance()->transport = FakeTransport::unreachable();

        $service = new ParInformationService;
        $first = $service->getForFederativeEntity($federativeEntity);
        $second = $service->getForFederativeEntity($federativeEntity);

        $this->assertTrue($first->unreachable);
        $this->assertTrue($second->unreachable);
    }

    function testConsistentPathIsAccepted()
    {
        $tree = \ConectaEnte\Dto\ParInformation::fromApiResponse($this->treeBody);

        $this->assertTrue($tree->isConsistentPath('1', '10', '100', '1000'));
    }

    function testInconsistentPathIsRejected()
    {
        $tree = \ConectaEnte\Dto\ParInformation::fromApiResponse($this->treeBody);

        $this->assertFalse($tree->isConsistentPath('1', '10', '100', '9999'));
        $this->assertFalse($tree->isConsistentPath('1', '99', '100', '1000'));
    }
}
