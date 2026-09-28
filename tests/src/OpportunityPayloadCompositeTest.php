<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Vocabulary\AffirmativeAction;
use ConectaEnte\Vocabulary\AffirmativeActionGroup;
use ConectaEnte\Vocabulary\CulturalStage;
use ConectaEnte\Vocabulary\FundingSource;
use ConectaEnte\Vocabulary\LegalQuota;
use ConectaEnte\Vocabulary\RegistrationChannel;
use ConectaEnte\Vocabulary\Segment;
use ConectaEnte\Vocabulary\TargetingField;
use ConectaEnte\Vocabulary\TargetingOption;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * Os multiselects e os quatro blocos do payload, e a conformidade das chaves que o contrato exige.
 */
class OpportunityPayloadCompositeTest extends TestCase
{
    use PublicationRequirementsFixtures;

    /** As 26 chaves do edital; as outras 4 do contrato são os ids do PAR, que a história 05 acrescenta. */
    const CONTRACT_KEYS = [
        'id', 'numero_e_titulo_edital', 'forma_de_execucao', 'status', 'data_publicacao_edital',
        'detalhamento_objeto', 'numero_previsto_vagas', 'valor_total_edital',
        'data_inicial_prazo_inscricao', 'data_final_prazo_inscricao', 'tipos_proponentes',
        'segmentos_artistico_culturais', 'segmento_artistico_cultural_especificar',
        'etapas_fazer_cultural', 'etapa_fazer_cultural_especificar',
        'pautas_especificas', 'pauta_especifica_especificar', 'categorias_edital',
        'recursos_territorios_prioritarios', 'links_da_pagina_pnab', 'pdf_edital',
        'recursos_outras_fontes', 'tipos_formas_inscricao', 'reserva_vagas_cotas',
        'outras_modalidades_acoes_afirmativas', 'ente_federado',
    ];

    function testSelectedOptionsGoAsLabelsJoinedByComma()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::SEGMENTS} = [Segment::THEATER->value, Segment::DANCE->value];

        $this->assertSame('Teatro, Dança', $this->payloadOf($opportunity)['segmentos_artistico_culturais']);
    }

    function testNotTargetedGoesAsTheFieldOwnText()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::CULTURAL_STAGES} = [TargetingOption::NOT_TARGETED->value];

        $this->assertSame(
            TargetingField::CULTURAL_STAGE->notTargetedText(),
            $this->payloadOf($opportunity)['etapas_fazer_cultural'],
        );
    }

    function testAllOptionsExpandsIncludingOtherAsTheCultEditaisDoes()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::SEGMENTS} = [TargetingOption::ALL_OPTIONS->value, Segment::THEATER->value];

        $expanded = $this->payloadOf($opportunity)['segmentos_artistico_culturais'];

        $this->assertStringContainsString(Segment::OTHER->text(), $expanded, 'O mapeador do CultEditais inclui "Outros (especificar)" na expansão.');
        $this->assertSame(count(Segment::cases()), substr_count($expanded, ', ') + 1);
    }

    function testValueOutsideTheVocabularyGoesRawAsTheCultEditaisDoes()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::THEMATIC_AGENDAS} = ['Cultura Digital', 'Humanidades'];

        $this->assertSame('Cultura Digital, Humanidades', $this->payloadOf($opportunity)['pautas_especificas']);
    }

    function testSpecificationIsEmptyWhenOtherIsNotSelected()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::SEGMENTS} = [Segment::THEATER->value];

        $this->assertSame('', $this->payloadOf($opportunity)['segmento_artistico_cultural_especificar']);
    }

    function testSpecificationCarriesTheTextWhenOtherIsSelected()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::SEGMENTS} = [Segment::OTHER->value];
        $opportunity->{CultBrMetadata::SEGMENTS_OTHER} = 'Palhaçaria';

        $this->assertSame('Palhaçaria', $this->payloadOf($opportunity)['segmento_artistico_cultural_especificar']);
    }

    function testSpecificationIsNullWhenOtherIsSelectedAndEmpty()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::SEGMENTS} = [Segment::OTHER->value];
        $opportunity->{CultBrMetadata::SEGMENTS_OTHER} = '  ';

        $this->assertNull(
            $this->payloadOf($opportunity)['segmento_artistico_cultural_especificar'],
            'Marcada e sem texto é dado que falta, para o envio nomeá-lo.',
        );
    }

    function testFundingSourcesGoInSnakeCaseWithoutTheScreenId()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::FUNDING_SOURCES} = [
            'houveUtilizacao' => 'sim',
            FundingSource::OWN_RESOURCES->value => 1500.5,
            FundingSource::OTHER_SOURCES->value => [['nomeFonte' => 'Fundação XYZ', 'valor' => 1000, '_id' => 'rf-1']],
        ];

        $sources = $this->payloadOf($opportunity)['recursos_outras_fontes'];

        $this->assertSame('sim', $sources['houve_utilizacao']);
        $this->assertSame('1500.50', $sources['recursos_proprios']);
        $this->assertNull($sources['convenios_parcerias']);
        $this->assertSame([['nome_fonte' => 'Fundação XYZ', 'valor' => '1000.00']], $sources['outras_fontes']);
    }

    function testRegistrationChannelsGoAsTypeAndDescription()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::REGISTRATION_CHANNELS} = [
            'previstasNoEdital' => 'sim',
            'formas' => [['tipo' => RegistrationChannel::MAIL->value, 'descricao' => 'Envelope no protocolo']],
        ];

        $this->assertSame(
            [['tipo' => 'correio', 'descricao' => 'Envelope no protocolo']],
            $this->payloadOf($opportunity)['tipos_formas_inscricao'],
        );
    }

    function testAnsweringNoLeavesTheChannelListEmpty()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::REGISTRATION_CHANNELS} = [
            'previstasNoEdital' => 'nao',
            'formas' => [['tipo' => RegistrationChannel::EMAIL->value, 'descricao' => 'editais@municipio.gov.br']],
        ];

        $this->assertSame(
            [],
            $this->payloadOf($opportunity)['tipos_formas_inscricao'],
            'Respondido "não", o que sobrou de uma resposta anterior não vai ao CultBR.',
        );
    }

    function testQuotasGoWithLabelAndSnakeCase()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::QUOTA_RESERVATION} = [
            ['label' => LegalQuota::BLACK_PEOPLE->text(), 'vagas' => 3, 'valorDestinado' => 300, 'naoAplicavel' => false],
        ];

        $this->assertSame(
            [['label' => 'Pessoas negras (pretas e pardas)', 'vagas' => 3, 'valor_destinado' => '300.00', 'nao_aplicavel' => false]],
            $this->payloadOf($opportunity)['reserva_vagas_cotas'],
        );
    }

    function testAffirmativeActionsCarryTheirGroupsAndDescription()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::AFFIRMATIVE_ACTIONS} = [
            'opcoes' => [AffirmativeAction::AGENT_BONUS->value],
            AffirmativeAction::AGENT_BONUS->value => [AffirmativeActionGroup::WOMEN->value],
            'outra_legislacao_descricao' => 'Lei municipal 123/2024',
        ];

        $actions = $this->payloadOf($opportunity)['outras_modalidades_acoes_afirmativas'];

        $this->assertSame(['bonus_agentes'], $actions['opcoes']);
        $this->assertSame(['mulheres'], $actions['bonus_agentes']);
        $this->assertSame('Lei municipal 123/2024', $actions['outra_legislacao_descricao']);
        $this->assertSame([], $actions['bonus_tematicas'], 'Opção não marcada vai como lista vazia, não nula.');
    }

    function testCompleteOpportunityFillsEveryContractKey()
    {
        $payload = $this->payloadOf($this->completelyFilled());

        $this->assertSame(self::CONTRACT_KEYS, array_keys($payload), 'São as 26 chaves do edital: as 30 do contrato menos as 4 do PAR.');

        foreach ($payload as $key => $value) {
            $this->assertNotNull($value, "Com a oportunidade completa, {$key} não sai nulo.");
        }
    }

    /** Oportunidade selada com todos os campos do edital preenchidos. */
    private function completelyFilled(): Opportunity
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::SEGMENTS} = [Segment::THEATER->value];
        $opportunity->{CultBrMetadata::SEGMENTS_OTHER} = '';
        $opportunity->{CultBrMetadata::CULTURAL_STAGES} = [CulturalStage::CREATION->value];
        $opportunity->{CultBrMetadata::THEMATIC_AGENDAS} = [TargetingOption::NOT_TARGETED->value];
        $opportunity->{CultBrMetadata::PRIORITY_TERRITORIES} = [TargetingOption::NOT_TARGETED->value];
        $opportunity->{CultBrMetadata::PUBLISHED_AT} = new \DateTime('2026-09-24 10:30:45');
        $opportunity->{CultBrMetadata::FUNDING_SOURCES} = ['houveUtilizacao' => 'nao'];
        $opportunity->{CultBrMetadata::REGISTRATION_CHANNELS} = ['previstasNoEdital' => 'nao', 'formas' => []];
        $opportunity->{CultBrMetadata::QUOTA_RESERVATION} = [
            ['label' => LegalQuota::OPEN_COMPETITION->text(), 'vagas' => 10, 'valorDestinado' => 1000, 'naoAplicavel' => false],
        ];
        $opportunity->{CultBrMetadata::AFFIRMATIVE_ACTIONS} = ['opcoes' => [AffirmativeAction::NOT_PLANNED->value]];
        $opportunity->registrationRanges = [['label' => 'Faixa 1', 'limit' => 10, 'value' => 1000]];

        return $opportunity;
    }

    private function sealed(int $status = Opportunity::STATUS_ENABLED): Opportunity
    {
        return $this->reloaded($this->sealedOpportunity($status));
    }

    private function payloadOf(Opportunity $opportunity): array
    {
        return Plugin::instance()->opportunityPayload()->build($opportunity);
    }
}
