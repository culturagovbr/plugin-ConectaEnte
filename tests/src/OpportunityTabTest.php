<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\FederativeEntitySeal;
use Doctrine\DBAL\Logging\DebugStack;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

class OpportunityTabTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    const COMPONENTS = ['conectaente--opportunity-tab', 'conectaente--opportunity-requirements', 'conectaente--targeting-multiselect'];

    function testEditPageImportsTheTabComponentsAndTheSealList()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->federativeSeal();

        $page = $this->editPage($this->createOpportunityWithSeal($seal, Opportunity::STATUS_DRAFT));

        $this->assertStringContainsString('<conectaente--opportunity-tab :entity="entity">', $page);
        foreach (self::COMPONENTS as $component) {
            $this->assertStringContainsString("\"{$component}\":", $page);
        }
        $this->assertContains($seal->id, $this->tabConfig($page)['federativeSealIds']);
    }

    function testEditPagePublishesWhatTheTabAndTheMultiselectNeed()
    {
        $this->loginAsSaasSuperAdmin();

        $page = $this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT));
        $config = $this->jsObject($page)['config'];

        $this->assertSame('Pessoa Jurídica', $config['conectaenteOpportunityTab']['legalEntityLabel']);
        $this->assertSame(
            ['notTargeted' => '__edital_nao_se_direciona__', 'allOptions' => '__todas_opcoes__'],
            $config['conectaenteTargetingMultiselect'],
        );
    }

    function testEditPageOfUnsealedOpportunityAlsoImportsTheTab()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->federativeSeal();

        $page = $this->editPage($this->createOpportunity(Opportunity::STATUS_DRAFT));

        $this->assertStringContainsString('<conectaente--opportunity-tab :entity="entity">', $page);
        $this->assertContains($seal->id, $this->tabConfig($page)['federativeSealIds']);
    }

    function testOwnerWhoIsNotAdministratorAlsoGetsTheTab()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->federativeSeal();
        $this->login($this->userDirector->createUser());
        $opportunity = $this->createOpportunity(Opportunity::STATUS_DRAFT);
        $this->app->disableAccessControl();
        $opportunity->createSealRelation($seal);
        $this->app->enableAccessControl();

        $page = $this->editPage($opportunity);

        $this->assertStringContainsString('<conectaente--opportunity-tab :entity="entity">', $page);
        $this->assertContains($seal->id, $this->tabConfig($page)['federativeSealIds']);
    }

    function testSealOfFederativeEntityInTheTrashIsLeftOut()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntityWithSeal($this->createSeal(), 'Município na lixeira', '11222333000144')->delete(true);
        $activeSeal = $this->federativeSeal();

        $sealIds = $this->tabConfig($this->editPage($this->createOpportunityWithSeal($activeSeal, Opportunity::STATUS_DRAFT)))['federativeSealIds'];

        $this->assertSame([$activeSeal->id], $sealIds);
    }

    function testWithoutAnActiveFederativeEntityTheListIsEmpty()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntityWithSeal($this->createSeal(), 'Município na lixeira', '11222333000144')->delete(true);

        $page = $this->editPage($this->createOpportunity(Opportunity::STATUS_DRAFT));

        $this->assertSame([], $this->tabConfig($page)['federativeSealIds']);
    }

    function testSealListTakesOneQueryWhateverTheNumberOfEntities()
    {
        $this->loginAsSaasSuperAdmin();
        $expected = [];
        foreach (range(1, 3) as $index) {
            $seal = $this->createSeal();
            $this->createFederativeEntityWithSeal($seal, "Município {$index}", sprintf('%014d', $index));
            $expected[] = $seal->id;
        }
        $this->app->em->clear();

        $sealIds = [];
        $queries = $this->queriesOf(function () use (&$sealIds) {
            $sealIds = $this->app->repo(FederativeEntitySeal::class)->findSealIdsOfEnabledEntities();
        });
        sort($sealIds);

        $this->assertCount(1, $queries);
        $this->assertSame($expected, $sealIds);
    }

    private function editPage(Opportunity $opportunity): string
    {
        $this->assertSame(200, $this->send($this->requestFactory->GET('opportunity', 'edit', [$opportunity->id])));

        return (string) $this->app->response->getBody();
    }

    private function tabConfig(string $page): array
    {
        return $this->jsObject($page)['config']['conectaenteOpportunityTab'];
    }

    private function queriesOf(callable $action): array
    {
        $configuration = $this->app->em->getConnection()->getConfiguration();
        $previousLogger = $configuration->getSQLLogger();
        $logger = new DebugStack();
        $configuration->setSQLLogger($logger);

        try {
            $action();
        } finally {
            $configuration->setSQLLogger($previousLogger);
        }

        return $logger->queries;
    }

    private function jsObject(string $page): array
    {
        preg_match('/var Mapas = (\{.*?\});\n/s', $page, $matches);

        return json_decode($matches[1] ?? '{}', true);
    }
}
