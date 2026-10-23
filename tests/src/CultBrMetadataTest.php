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
        'conectaente_executionType' => 'select',
        'conectaente_segments' => 'multiselect',
        'conectaente_segmentsOther' => 'string',
        'conectaente_culturalStages' => 'multiselect',
        'conectaente_culturalStagesOther' => 'string',
        'conectaente_thematicAgendas' => 'multiselect',
        'conectaente_thematicAgendasOther' => 'string',
        'conectaente_priorityTerritories' => 'multiselect',
        'conectaente_fundingSources' => 'json',
        'conectaente_quotaReservation' => 'json',
        'conectaente_registrationChannels' => 'json',
        'conectaente_affirmativeActions' => 'json',
        'conectaente_legalEntityTypes' => 'multiselect',
        'conectaente_publishedAt' => 'datetime',
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
        $this->assertSame('Tipo de Edital', $description['conectaente_executionType']['label']);
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

    function testPublishedAtReachesThePageAsADateTime()
    {
        $description = Opportunity::getPropertiesMetadata()['conectaente_publishedAt'];

        $this->assertSame('datetime', $description['type']);
        $this->assertSame('datetime', $description['field_type']);
    }

    function testPublishedAtAcceptsTheDateTextThePageSends()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $opportunity->conectaente_publishedAt = '2026-09-24 10:30';
        $opportunity->save(true);

        $this->assertSame('2026-09-24 10:30:00', $this->reloaded($opportunity)->conectaente_publishedAt->format('Y-m-d H:i:s'));
    }

    function testPublishedAtClearedByThePageIsStoredAsNull()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $opportunity->conectaente_publishedAt = new \DateTime('2026-09-24 10:30:00');
        $opportunity->save(true);

        $opportunity->conectaente_publishedAt = '';
        $opportunity->save(true);

        $this->assertNull($this->reloaded($opportunity)->conectaente_publishedAt);
    }

    function testPublishedAtRefusesAnythingButDateOrText()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $this->expectException(\InvalidArgumentException::class);

        $opportunity->conectaente_publishedAt = ['locale' => 'pt-BR', '_date' => '2026-09-24T13:30:00.000Z'];
        $opportunity->save(true);
    }

    function testExecutionTypeOptionsKeepTheFixedValueAsKey()
    {
        $options = $this->definition('conectaente_executionType')->options;

        $this->assertSame('Execução cultural', array_key_first($options));
        $this->assertSame('Execução cultural', $options['Execução cultural']);
        $this->assertCount(12, $options);
    }

    function testSegmentOptionsStartWithBothSyntheticOptions()
    {
        $options = $this->definition('conectaente_segments')->options;

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
            'conectaente_culturalStages' => [CulturalStage::cases(), 'Edital não se direciona a etapa específica'],
            'conectaente_thematicAgendas' => [ThematicAgenda::cases(), 'Edital não se direciona a pautas específicas'],
            'conectaente_priorityTerritories' => [PriorityTerritory::cases(), 'Edital não se direciona a territórios específicos'],
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
            $this->definition('conectaente_legalEntityTypes')->options
        );
    }

    function testValuesRoundTripThroughTheDatabase()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $opportunity->conectaente_executionType = 'Bolsa cultural';
        $opportunity->conectaente_segments = ['Teatro', 'Outros'];
        $opportunity->conectaente_segmentsOther = 'Palhaçaria';
        $opportunity->conectaente_fundingSources = ['houveUtilizacao' => 'sim', 'recursosProprios' => 1500.5];
        $opportunity->conectaente_quotaReservation = [['label' => 'Ampla concorrência', 'vagas' => 10]];
        $opportunity->conectaente_publishedAt = new \DateTime('2026-09-24 10:30:45');
        $opportunity->save(true);

        $reloaded = $this->reloaded($opportunity);

        $this->assertSame('Bolsa cultural', $reloaded->conectaente_executionType);
        $this->assertSame(['Teatro', 'Outros'], $reloaded->conectaente_segments);
        $this->assertSame('Palhaçaria', $reloaded->conectaente_segmentsOther);
        $this->assertEquals((object) ['houveUtilizacao' => 'sim', 'recursosProprios' => 1500.5], $reloaded->conectaente_fundingSources);
        $this->assertEquals([(object) ['label' => 'Ampla concorrência', 'vagas' => 10]], $reloaded->conectaente_quotaReservation);
        $this->assertSame('2026-09-24 10:30:45', $reloaded->conectaente_publishedAt->format('Y-m-d H:i:s'));
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
