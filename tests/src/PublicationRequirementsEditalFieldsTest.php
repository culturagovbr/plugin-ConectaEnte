<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Vocabulary\CulturalStage;
use ConectaEnte\Vocabulary\Segment;
use ConectaEnte\Vocabulary\TargetingOption;
use ConectaEnte\Vocabulary\ThematicAgenda;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

class PublicationRequirementsEditalFieldsTest extends TestCase
{
    use PublicationRequirementsFixtures;

    function testExecutionTypeIsRequiredAndMustBeInTheList()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_executionType = null;
        $this->assertSame(['conectaente_executionType' => ['O campo tipo de edital é obrigatório.']], $this->missing($opportunity));

        $opportunity->conectaente_executionType = 'Pregão';
        $this->assertSame(['conectaente_executionType' => [
            'O campo tipo de edital é obrigatório.',
            'A opção "Pregão" de tipo de edital não tem correspondente no CultBR.',
        ]], $this->missing($opportunity));
    }

    public static function targetingFields(): array
    {
        return [
            'segmento' => ['conectaente_segments', 'O campo segmento artístico-cultural é obrigatório.'],
            'etapa' => ['conectaente_culturalStages', 'O campo etapa do fazer cultural é obrigatório.'],
            'pauta' => ['conectaente_thematicAgendas', 'O campo pauta temática é obrigatório.'],
            'território' => ['conectaente_priorityTerritories', 'O campo território é obrigatório.'],
        ];
    }

    #[DataProvider('targetingFields')]
    function testTargetingFieldIsRequiredAndNotTargetedCounts(string $key, string $message)
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->$key = [];
        $this->assertSame([$key => [$message]], $this->missing($opportunity));

        $opportunity->$key = [TargetingOption::NOT_TARGETED->value];
        $this->assertSame([], $this->missing($opportunity));
    }

    function testTargetingOptionOutsideTheListIsNamedAndDoesNotCount()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_segments = [Segment::COLLECTIONS->value, 'Circo digital'];
        $this->assertSame(
            ['A opção "Circo digital" de segmento artístico-cultural não tem correspondente no CultBR.'],
            $this->missing($opportunity)['conectaente_segments'] ?? null,
        );

        $opportunity->conectaente_segments = ['Circo digital'];
        $this->assertSame([
            'O campo segmento artístico-cultural é obrigatório.',
            'A opção "Circo digital" de segmento artístico-cultural não tem correspondente no CultBR.',
        ], $this->missing($opportunity)['conectaente_segments'] ?? null);
    }

    function testAllOptionsExistsOnlyInSegments()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_segments = [TargetingOption::ALL_OPTIONS->value];
        $opportunity->conectaente_culturalStages = [TargetingOption::ALL_OPTIONS->value];

        $this->assertSame(['conectaente_culturalStages' => [
            'O campo etapa do fazer cultural é obrigatório.',
            'A opção "__todas_opcoes__" de etapa do fazer cultural não tem correspondente no CultBR.',
        ]], $this->missing($opportunity));
    }

    public static function otherSpecifications(): array
    {
        return [
            'segmento' => ['conectaente_segments', Segment::OTHER->value, 'conectaente_segmentsOther', 'O campo especificar segmento artístico-cultural é obrigatório quando "Outros (especificar)" é selecionado.'],
            'etapa' => ['conectaente_culturalStages', CulturalStage::OTHER->value, 'conectaente_culturalStagesOther', 'O campo especificar etapa do fazer cultural é obrigatório quando "Outra (especificar)" é selecionada.'],
            'pauta' => ['conectaente_thematicAgendas', ThematicAgenda::OTHER->value, 'conectaente_thematicAgendasOther', 'O campo especificar pauta temática é obrigatório quando "Outra (especificar)" é selecionada.'],
        ];
    }

    #[DataProvider('otherSpecifications')]
    function testOtherOptionRequiresItsSpecification(string $key, string $otherValue, string $otherKey, string $message)
    {
        $opportunity = $this->completeOpportunity();
        $opportunity->$key = [$otherValue];

        $opportunity->$otherKey = '   ';
        $this->assertSame([$otherKey => [$message]], $this->missing($opportunity));

        $opportunity->$otherKey = 'Arte com drones';
        $this->assertSame([], $this->missing($opportunity));
    }

    function testFundingSourcesNeedAnAnswer()
    {
        $opportunity = $this->completeOpportunity();
        $expected = ['conectaente_fundingSources' => ['O campo "Houve utilização de recursos de outras fontes?" é obrigatório.']];

        $opportunity->conectaente_fundingSources = null;
        $this->assertSame($expected, $this->missing($opportunity));

        $opportunity->conectaente_fundingSources = ['houveUtilizacao' => 'talvez'];
        $this->assertSame($expected, $this->missing($opportunity));
    }

    function testFundingSourcesAnsweredYesNeedASource()
    {
        $opportunity = $this->completeOpportunity();

        $expected = ['conectaente_fundingSources' => ['Selecione pelo menos uma fonte de recurso para continuar.']];

        $opportunity->conectaente_fundingSources = ['houveUtilizacao' => 'sim'];
        $this->assertSame($expected, $this->missing($opportunity));

        $opportunity->conectaente_fundingSources = ['houveUtilizacao' => 'sim', 'outrasFontes' => []];
        $this->assertSame($expected, $this->missing($opportunity));

        $opportunity->conectaente_fundingSources = ['houveUtilizacao' => 'sim', 'emendasParlamentares' => 0];
        $this->assertSame([], $this->missing($opportunity));
    }

    function testOtherFundingSourcesNeedAName()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_fundingSources = ['houveUtilizacao' => 'sim', 'outrasFontes' => [['nomeFonte' => ' ', 'valor' => 500]]];
        $this->assertSame(['conectaente_fundingSources' => ['Preencha o nome de pelo menos uma fonte em "Recursos de outras fontes".']], $this->missing($opportunity));

        $opportunity->conectaente_fundingSources = ['houveUtilizacao' => 'sim', 'outrasFontes' => [['nomeFonte' => 'Fundo municipal', 'valor' => 500]]];
        $this->assertSame([], $this->missing($opportunity));
    }

    function testRegistrationChannelsNeedAnAnswer()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_registrationChannels = null;

        $this->assertSame(['conectaente_registrationChannels' => ['O campo "Formas de inscrição previstas no edital" é obrigatório.']], $this->missing($opportunity));
    }

    function testRegistrationChannelsAnsweredYesNeedAChannelWithDescription()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => []];
        $this->assertSame(['conectaente_registrationChannels' => ['Selecione pelo menos uma forma de inscrição para continuar.']], $this->missing($opportunity));

        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => [['tipo' => 'presencial', 'descricao' => '  ']]];
        $this->assertSame(['conectaente_registrationChannels' => ['Preencha a descrição de cada forma de inscrição marcada.']], $this->missing($opportunity));

        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => [['tipo' => 'presencial', 'descricao' => ''], ['tipo' => 'oral', 'descricao' => '']]];
        $this->assertSame(['conectaente_registrationChannels' => ['Preencha a descrição de cada forma de inscrição marcada.']], $this->missing($opportunity));

        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => [['tipo' => 'presencial', 'descricao' => 'Na secretaria de cultura']]];
        $this->assertSame([], $this->missing($opportunity));
    }

    function testRegistrationChannelNeedsATypeFromTheList()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => [['tipo' => 'correspondencia', 'descricao' => 'Caixa postal 10']]];
        $this->assertSame(['conectaente_registrationChannels' => ['A forma de inscrição "correspondencia" não tem correspondente no CultBR.']], $this->missing($opportunity));

        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => [['descricao' => 'Caixa postal 10']]];
        $this->assertSame(['conectaente_registrationChannels' => ['Escolha o tipo de cada forma de inscrição marcada.']], $this->missing($opportunity));
    }

    function testEmailChannelNeedsAValidAddressUnderItsOwnKey()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => [['tipo' => 'email', 'descricao' => 'secretaria de cultura']]];
        $this->assertSame(['conectaente_registrationChannelsEmail' => ['Informe um e-mail válido.']], $this->missing($opportunity));

        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => [['tipo' => 'email', 'descricao' => 'editais@cultura.gov.br']]];
        $this->assertSame([], $this->missing($opportunity));
    }

    function testAffirmativeActionsNeedAKnownOption()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_affirmativeActions = ['opcoes' => []];
        $this->assertSame(['conectaente_affirmativeActions' => ['Selecione pelo menos uma opção.']], $this->missing($opportunity));

        $opportunity->conectaente_affirmativeActions = ['opcoes' => ['cotas_raciais']];
        $this->assertSame(['conectaente_affirmativeActions' => [
            'Selecione pelo menos uma opção.',
            'A ação afirmativa "cotas_raciais" não tem correspondente no CultBR.',
        ]], $this->missing($opportunity));
    }

    function testMalformedAffirmativeActionEntryIsIgnored()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_affirmativeActions = ['opcoes' => [['bonus_agentes']]];

        $this->assertSame(['conectaente_affirmativeActions' => ['Selecione pelo menos uma opção.']], $this->missing($opportunity));
    }

    function testAffirmativeActionWithGroupsNeedsKnownGroups()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_affirmativeActions = ['opcoes' => ['bonus_agentes']];
        $this->assertSame(['conectaente_affirmativeActions' => ['Por favor, selecione pelo menos uma subcategoria.']], $this->missing($opportunity));

        $opportunity->conectaente_affirmativeActions = ['opcoes' => ['bonus_agentes'], 'bonus_agentes' => ['pessoas_negras', 'quilombolas']];
        $this->assertSame(['conectaente_affirmativeActions' => ['O grupo "quilombolas" não tem correspondente no CultBR.']], $this->missing($opportunity));

        $opportunity->conectaente_affirmativeActions = ['opcoes' => ['bonus_agentes'], 'bonus_agentes' => ['pessoas_negras']];
        $this->assertSame([], $this->missing($opportunity));
    }

    function testOtherLegislationNeedsADescriptionUpTo140Characters()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_affirmativeActions = ['opcoes' => ['outra_legislacao'], 'outra_legislacao_descricao' => ''];
        $this->assertSame(['conectaente_affirmativeActions' => ['Por favor, preencha a descrição.']], $this->missing($opportunity));

        $opportunity->conectaente_affirmativeActions = ['opcoes' => ['outra_legislacao'], 'outra_legislacao_descricao' => str_repeat('á', 141)];
        $this->assertSame(['conectaente_affirmativeActions' => ['A descrição da outra ação afirmativa deve ter no máximo 140 caracteres.']], $this->missing($opportunity));

        $opportunity->conectaente_affirmativeActions = ['opcoes' => ['outra_legislacao'], 'outra_legislacao_descricao' => str_repeat('á', 140)];
        $this->assertSame([], $this->missing($opportunity));
    }
}
