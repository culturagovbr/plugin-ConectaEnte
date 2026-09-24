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
}
