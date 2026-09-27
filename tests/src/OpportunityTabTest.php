<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Services\FundingSourceName;
use ConectaEnte\Vocabulary\FundingSource;
use ConectaEnte\Vocabulary\RegistrationChannel;
use Doctrine\DBAL\Logging\DebugStack;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

class OpportunityTabTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    const COMPONENTS = ['conectaente--opportunity-tab', 'conectaente--opportunity-requirements', 'conectaente--targeting-multiselect', 'conectaente--quota-reservation', 'conectaente--registration-channels', 'conectaente--affirmative-actions', 'conectaente--funding-sources'];

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

    function testSealedOpportunityGetsTheRulesFieldInTheTab()
    {
        $this->loginAsSaasSuperAdmin();

        $page = $this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT));

        $this->assertStringContainsString('group-name="rules"', $this->tabTemplate($page), 'O regulamento é editável na aba, para a pendência ter destino.');
        $this->assertStringContainsString('"entity-file":', $page);
    }

    function testSealedOpportunityGetsTheProponentTypesFieldInTheTab()
    {
        $this->loginAsSaasSuperAdmin();

        $page = $this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT));

        $template = $this->tabTemplate($page);
        $proponentTypes = strpos($template, 'prop="registrationProponentTypes"');
        $legalEntity = strpos($template, 'prop="' . CultBrMetadata::LEGAL_ENTITY_TYPES . '"');

        $this->assertNotFalse($proponentTypes, 'Os tipos de proponente são editáveis na aba, para a pendência ter destino.');
        $this->assertLessThan($legalEntity, $proponentTypes, 'O campo vem antes do multiselect que depende dele.');
    }

    function testSealedOpportunityGetsBothRegistrationDatesInTheTab()
    {
        $this->loginAsSaasSuperAdmin();

        $page = $this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT));

        $template = $this->tabTemplate($page);

        $this->assertStringContainsString('prop="registrationFrom"', $template, 'A pendência da data inicial também precisa de destino na aba.');
        $this->assertStringContainsString('prop="registrationTo"', $template);
    }

    function testEndDateFollowsTheCoreConditionForContinuousFlow()
    {
        $this->loginAsSaasSuperAdmin();

        $page = $this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT));

        $this->assertStringContainsString(
            'v-if="!entity.isContinuousFlow || entity.hasEndDate"',
            $this->tabTemplate($page),
            'Em fluxo contínuo sem data final o core esconde o campo, e a aba segue a mesma condição.',
        );
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

    function testTabShowsTheCoreFieldsTheQuotaRuleDependsOn()
    {
        $this->loginAsSaasSuperAdmin();

        $page = $this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT));

        $template = $this->tabTemplate($page);

        foreach (['vacancies', 'totalResource'] as $field) {
            $this->assertStringContainsString(
                "prop=\"{$field}\"",
                $template,
                "A soma das cotas é conferida contra {$field}: o campo fica ao lado da tabela, não em outra aba.",
            );
        }
    }

    function testBlocksTakeTheirLabelsFromTheVocabulary()
    {
        $this->loginAsSaasSuperAdmin();

        $config = $this->jsObject($this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT)))['config'];

        $this->assertSame(
            array_map(fn(RegistrationChannel $channel) => $channel->label(), RegistrationChannel::cases()),
            array_column($config['conectaenteRegistrationChannels']['channels'], 'label'),
        );
        $this->assertSame(
            array_map(fn(FundingSource $source) => $source->label(), FundingSource::cases()),
            array_column($config['conectaenteFundingSources']['sources'], 'label'),
            'Os rótulos da tela saem do vocabulário; texts.php não os repete.',
        );
        $this->assertSame(FundingSourceName::MAX_LENGTH, $config['conectaenteFundingSources']['nameMaxLength']);
    }

    function testQuotaReservationTakesItsLabelsFromTheVocabularyInPortuguese()
    {
        $this->loginAsSaasSuperAdmin();

        $page = $this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT));
        $config = $this->jsObject($page)['config']['conectaenteQuotaReservation'];

        $this->assertSame(
            ['Pessoas negras (pretas e pardas)', 'Pessoas indígenas', 'Pessoas com deficiência'],
            $config['legalQuotas'],
            'As três cotas legais vão na ordem da lei, porque a regra as identifica pela posição.',
        );
        $this->assertSame('Ampla concorrência', $config['openCompetition']);
        $this->assertSame(
            array_keys($config['labels']),
            array_values($config['labels']),
            'Sem tradução ativa, o texto exibido é o mesmo valor gravado; o que vai ao payload é a chave.',
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


    /** O template da aba, como a página o entrega ao cliente. */
    private function tabTemplate(string $page): string
    {
        preg_match('/"conectaente--opportunity-tab":("(?:[^"\\\\]|\\\\.)*")/', $page, $matches);

        return json_decode($matches[1] ?? '""');
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

}
