<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Vocabulary\CulturalStage;
use ConectaEnte\Vocabulary\PriorityTerritory;
use ConectaEnte\Vocabulary\Segment;
use ConectaEnte\Vocabulary\ThematicAgenda;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;

class CultBrMetadataTest extends TestCase
{
    use ConectaEnteFixtures;

    const TYPES = [
        'conectaenteExecutionType' => 'select',
        'conectaenteSegments' => 'multiselect',
        'conectaenteSegmentsOther' => 'string',
        'conectaenteCulturalStages' => 'multiselect',
        'conectaenteCulturalStagesOther' => 'string',
        'conectaenteThematicAgendas' => 'multiselect',
        'conectaenteThematicAgendasOther' => 'string',
        'conectaentePriorityTerritories' => 'multiselect',
        'conectaenteFundingSources' => 'json',
        'conectaenteQuotaReservation' => 'json',
        'conectaenteRegistrationChannels' => 'json',
        'conectaenteAffirmativeActions' => 'json',
        'conectaenteLegalEntityTypes' => 'multiselect',
        'conectaentePublishedAt' => 'DateTime',
    ];

    function testEveryKeyIsRegisteredOnOpportunityWithItsType()
    {
        $types = array_map(fn($key) => $this->definition($key)?->type, array_keys(self::TYPES));

        $this->assertSame(array_values(self::TYPES), $types);
    }

    function testKeysReachTheEntityDescriptionSentToThePage()
    {
        $description = Opportunity::getPropertiesMetadata();

        foreach (array_keys(self::TYPES) as $key) {
            $this->assertArrayHasKey($key, $description);
        }
        $this->assertSame('Tipo de Edital', $description['conectaenteExecutionType']['label']);
    }

    function testLabelsAreTheCultEditaisOnes()
    {
        $labels = array_map(fn($key) => $this->definition($key)->label, array_keys(self::TYPES));

        $this->assertSame([
            'Tipo de Edital',
            'Segmento artístico-cultural',
            'Especificar segmento artístico-cultural',
            'Etapa do fazer cultural',
            'Especificar etapa do fazer cultural',
            'Pauta temática',
            'Especificar pauta temática',
            'Território',
            'Houve utilização de recursos de outras fontes?',
            'Reserva de vagas (cotas)',
            'Formas de inscrição previstas no edital',
            'Outras modalidades de ações afirmativas',
            'Tipo de pessoa jurídica',
            'Data de publicação do edital',
        ], $labels);
    }

    function testPublishedAtRendersAsADateTimeField()
    {
        $this->assertSame('datetime', $this->definition('conectaentePublishedAt')->field_type);
    }

    function testExecutionTypeOptionsKeepTheFixedValueAsKey()
    {
        $options = $this->definition('conectaenteExecutionType')->options;

        $this->assertSame('Execução cultural', array_key_first($options));
        $this->assertSame('Execução cultural', $options['Execução cultural']);
        $this->assertCount(12, $options);
    }

    function testSegmentOptionsStartWithBothSyntheticOptions()
    {
        $options = $this->definition('conectaenteSegments')->options;

        $this->assertSame(
            ['__edital_nao_se_direciona__', '__todas_opcoes__', ...array_column(Segment::cases(), 'value')],
            array_keys($options)
        );
        $this->assertSame('Edital não se direciona a segmentos específicos', $options['__edital_nao_se_direciona__']);
        $this->assertSame('Todas as opções', $options['__todas_opcoes__']);
        $this->assertSame('Outros (especificar)', $options['Outros']);
    }

    function testOtherMultiselectsOfferOnlyTheirOwnNotTargetedOption()
    {
        $expected = [
            'conectaenteCulturalStages' => [CulturalStage::cases(), 'Edital não se direciona a etapa específica'],
            'conectaenteThematicAgendas' => [ThematicAgenda::cases(), 'Edital não se direciona a pautas específicas'],
            'conectaentePriorityTerritories' => [PriorityTerritory::cases(), 'Edital não se direciona a territórios específicos'],
        ];

        foreach ($expected as $key => [$cases, $notTargeted]) {
            $options = $this->definition($key)->options;

            $this->assertSame(['__edital_nao_se_direciona__', ...array_column($cases, 'value')], array_keys($options), $key);
            $this->assertSame($notTargeted, $options['__edital_nao_se_direciona__'], $key);
        }
    }

    function testLegalEntityTypeOptionsAreTheVocabulary()
    {
        $this->assertSame(
            ['Com fins lucrativos' => 'Com fins lucrativos', 'Sem fins lucrativos' => 'Sem fins lucrativos'],
            $this->definition('conectaenteLegalEntityTypes')->options
        );
    }

    function testValuesRoundTripThroughTheDatabase()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $opportunity->conectaenteExecutionType = 'Bolsa cultural';
        $opportunity->conectaenteSegments = ['Teatro', 'Outros'];
        $opportunity->conectaenteSegmentsOther = 'Palhaçaria';
        $opportunity->conectaenteFundingSources = ['houveUtilizacao' => 'sim', 'recursosProprios' => 1500.5];
        $opportunity->conectaenteQuotaReservation = [['label' => 'Ampla concorrência', 'vagas' => 10]];
        $opportunity->conectaentePublishedAt = new \DateTime('2026-09-24 10:30:00');
        $opportunity->save(true);

        $reloaded = $this->reloaded($opportunity);

        $this->assertSame('Bolsa cultural', $reloaded->conectaenteExecutionType);
        $this->assertSame(['Teatro', 'Outros'], $reloaded->conectaenteSegments);
        $this->assertSame('Palhaçaria', $reloaded->conectaenteSegmentsOther);
        $this->assertEquals((object) ['houveUtilizacao' => 'sim', 'recursosProprios' => 1500.5], $reloaded->conectaenteFundingSources);
        $this->assertEquals([(object) ['label' => 'Ampla concorrência', 'vagas' => 10]], $reloaded->conectaenteQuotaReservation);
        $this->assertSame('2026-09-24 10:30:00', $reloaded->conectaentePublishedAt->format('Y-m-d H:i:s'));
    }

    function testRegistrationAddsNoValidationToAnyOpportunity()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->reloaded($this->createOpportunity());

        $pluginErrors = array_filter(array_keys($opportunity->validationErrors), fn($key) => str_starts_with($key, 'conectaente'));

        $this->assertSame([], $pluginErrors);
    }

    private function definition(string $key)
    {
        return $this->app->getRegisteredMetadataByMetakey($key, Opportunity::class);
    }
}
