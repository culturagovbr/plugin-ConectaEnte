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

    const COMPONENTS = ['conectaente--opportunity-tab', 'conectaente--opportunity-requirements', 'conectaente--targeting-multiselect', 'conectaente--quota-reservation', 'conectaente--registration-channels', 'conectaente--affirmative-actions', 'conectaente--funding-sources', 'conectaente--proponent-types', 'conectaente--opportunity-ranges', 'conectaente--par-selection', 'conectaente--federative-entity-par'];

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
        $proponentTypes = strpos($template, '<conectaente--proponent-types');
        $legalEntity = strpos($template, 'prop="' . CultBrMetadata::LEGAL_ENTITY_TYPES . '"');

        $this->assertNotFalse($proponentTypes, 'O campo traz a vinculação de agente coletivo, como no CultEditais.');
        $this->assertStringNotContainsString('<opportunity-proponent-types', $template, 'O componente do core grava a cada clique e não serve ao edital selado.');
        $this->assertLessThan($legalEntity, $proponentTypes, 'O campo vem antes do multiselect que depende dele.');
    }

    function testTheFormShowsTheFieldsInTheOrderTheListReadsThem()
    {
        $this->loginAsSaasSuperAdmin();

        $template = $this->tabTemplate($this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT)));
        $positions = [];

        foreach (['rules', 'registrationFrom', 'registrationTo', 'registrationProponentTypes'] as $field) {
            $positions[$field] = strpos($template, "showsField('{$field}')");
        }

        $sorted = $positions;
        asort($sorted);

        $this->assertSame(array_keys($positions), array_keys($sorted), 'O formulário segue a ordem do CultEditais: regulamento, datas e só então os tipos de proponente.');
    }

    function testTheFormFollowsTheGroupTheUserOpened()
    {
        $this->loginAsSaasSuperAdmin();

        $template = $this->tabTemplate($this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT)));

        $this->assertStringContainsString("showsField('registrationProponentTypes')", $template, 'Campo do core só aparece no grupo dele.');
        $this->assertStringContainsString("showsField('" . CultBrMetadata::SEGMENTS . "')", $template, 'Campo do plugin idem, e o card inteiro depende dele.');
        $this->assertStringContainsString("showsAnyField(['vacancies'", $template, 'Card misto aparece enquanto tiver algum campo do grupo.');
    }

    function testFieldWhoseComponentIsNotFromTheCoreStillPublishesItsAnchor()
    {
        $this->loginAsSaasSuperAdmin();

        $page = $this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT));

        foreach (['conectaente--proponent-types' => 'registrationProponentTypes', 'conectaente--opportunity-ranges' => 'registrationRanges'] as $component => $field) {
            $this->assertStringContainsString(
                "data-field=\"{$field}\"",
                $this->componentTemplate($page, $component),
                'Sem a âncora, a pendência fica sem destino na lista.',
            );
        }
    }

    function testRangesFieldSitsNextToTheTotalsTheRuleComparesItWith()
    {
        $this->loginAsSaasSuperAdmin();

        $template = $this->tabTemplate($this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT)));
        $ranges = strpos($template, '<conectaente--opportunity-ranges');

        $this->assertNotFalse($ranges, 'As duas pendências de soma precisam de um campo na aba.');
        $this->assertStringNotContainsString('<opportunity-ranges-config', $template, 'O componente do core grava a cada edição e não serve ao edital selado.');
        $this->assertGreaterThan(strpos($template, 'prop="totalResource"'), $ranges, 'As faixas vêm depois dos totais com que a regra as compara.');
        $this->assertLessThan(strpos($template, '<conectaente--quota-reservation'), $ranges);
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
            '!entity.isContinuousFlow || entity.hasEndDate',
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


    function testTheParCardOpensTheTab()
    {
        $this->loginAsSaasSuperAdmin();

        $template = $this->tabTemplate($this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT)));
        $par = strpos($template, '<conectaente--par-selection');

        $this->assertNotFalse($par, 'Sem campo na aba, as quatro pendências do PAR não teriam onde ser resolvidas.');
        $this->assertLessThan(strpos($template, '<h3>Identificação do edital</h3>'), $par, 'O PAR é o primeiro card, em qualquer grupo aberto.');
        $this->assertStringNotContainsString('<mc-federative-entity-par', $template, 'O componente do tema depende do AldirBlanc; o plugin usa a própria cópia.');
    }

    function testTheParCardFollowsTheGroupTheUserOpened()
    {
        $this->loginAsSaasSuperAdmin();

        $page = $this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT));

        $this->assertStringContainsString('showsAnyField(config.parFields)', $this->tabTemplate($page), 'O card do PAR some com o filtro, como os outros.');
        $this->assertSame(CultBrMetadata::PAR_KEYS, $this->tabConfig($page)['parFields'], 'A tela classifica as quatro como campo do plugin por esta lista: elas não têm o prefixo.');
    }

    function testEachParLevelPublishesItsAnchor()
    {
        $this->loginAsSaasSuperAdmin();

        $page = $this->editPage($this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT));
        $template = $this->componentTemplate($page, 'conectaente--federative-entity-par');

        foreach (CultBrMetadata::PAR_KEYS as $key) {
            $this->assertStringContainsString("data-field=\"{$key}\"", $template, 'Cada pendência do PAR rola até o próprio select.');
        }
    }


    /** O template da aba, como a página o entrega ao cliente. */
    private function tabTemplate(string $page): string
    {
        return $this->componentTemplate($page, 'conectaente--opportunity-tab');
    }

    private function componentTemplate(string $page, string $component): string
    {
        preg_match('/"' . preg_quote($component, '/') . '":("(?:[^"\\\\]|\\\\.)*")/', $page, $matches);

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
