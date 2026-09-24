<?php

namespace ConectaEnte\Metadata;

use ConectaEnte\Plugin;
use ConectaEnte\Vocabulary\CulturalStage;
use ConectaEnte\Vocabulary\ExecutionType;
use ConectaEnte\Vocabulary\LegalEntityType;
use ConectaEnte\Vocabulary\PriorityTerritory;
use ConectaEnte\Vocabulary\Segment;
use ConectaEnte\Vocabulary\TargetingField;
use ConectaEnte\Vocabulary\TargetingOption;
use ConectaEnte\Vocabulary\ThematicAgenda;
use MapasCulturais\i;

final class CultBrMetadata
{
    const EXECUTION_TYPE = 'conectaenteExecutionType';
    const SEGMENTS = 'conectaenteSegments';
    const SEGMENTS_OTHER = 'conectaenteSegmentsOther';
    const CULTURAL_STAGES = 'conectaenteCulturalStages';
    const CULTURAL_STAGES_OTHER = 'conectaenteCulturalStagesOther';
    const THEMATIC_AGENDAS = 'conectaenteThematicAgendas';
    const THEMATIC_AGENDAS_OTHER = 'conectaenteThematicAgendasOther';
    const PRIORITY_TERRITORIES = 'conectaentePriorityTerritories';
    const FUNDING_SOURCES = 'conectaenteFundingSources';
    const QUOTA_RESERVATION = 'conectaenteQuotaReservation';
    const REGISTRATION_CHANNELS = 'conectaenteRegistrationChannels';
    const AFFIRMATIVE_ACTIONS = 'conectaenteAffirmativeActions';
    const LEGAL_ENTITY_TYPES = 'conectaenteLegalEntityTypes';
    const PUBLISHED_AT = 'conectaentePublishedAt';

    /**
     * Registra na oportunidade os campos que o CultBR exige; quem os torna obrigatórios é a regra de publicação.
     */
    public static function register(Plugin $plugin): void
    {
        $plugin->registerOpportunityMetadata(self::EXECUTION_TYPE, [
            'label' => i::__('Tipo de Edital'),
            'type' => 'select',
            'options' => self::options(ExecutionType::cases()),
        ]);

        self::registerMultiselect($plugin, self::SEGMENTS, i::__('Segmento artístico-cultural'), self::targetingOptions(Segment::cases(), TargetingField::SEGMENT, includesAllOptions: true));
        self::registerText($plugin, self::SEGMENTS_OTHER, i::__('Especificar segmento artístico-cultural'));

        self::registerMultiselect($plugin, self::CULTURAL_STAGES, i::__('Etapa do fazer cultural'), self::targetingOptions(CulturalStage::cases(), TargetingField::CULTURAL_STAGE));
        self::registerText($plugin, self::CULTURAL_STAGES_OTHER, i::__('Especificar etapa do fazer cultural'));

        self::registerMultiselect($plugin, self::THEMATIC_AGENDAS, i::__('Pauta temática'), self::targetingOptions(ThematicAgenda::cases(), TargetingField::THEMATIC_AGENDA));
        self::registerText($plugin, self::THEMATIC_AGENDAS_OTHER, i::__('Especificar pauta temática'));

        self::registerMultiselect($plugin, self::PRIORITY_TERRITORIES, i::__('Território'), self::targetingOptions(PriorityTerritory::cases(), TargetingField::PRIORITY_TERRITORY));

        self::registerJson($plugin, self::FUNDING_SOURCES, i::__('Houve utilização de recursos de outras fontes?'));
        self::registerJson($plugin, self::QUOTA_RESERVATION, i::__('Reserva de vagas (cotas)'));
        self::registerJson($plugin, self::REGISTRATION_CHANNELS, i::__('Formas de inscrição previstas no edital'));
        self::registerJson($plugin, self::AFFIRMATIVE_ACTIONS, i::__('Outras modalidades de ações afirmativas'));

        self::registerMultiselect($plugin, self::LEGAL_ENTITY_TYPES, i::__('Tipo de pessoa jurídica'), self::options(LegalEntityType::cases()));

        $plugin->registerOpportunityMetadata(self::PUBLISHED_AT, [
            'label' => i::__('Data de publicação do edital'),
            'type' => 'DateTime',
            // o entity-field escolhe o campo pelo field_type em minúsculas
            'field_type' => 'datetime',
        ]);
    }

    private static function registerMultiselect(Plugin $plugin, string $key, string $label, array $options): void
    {
        $plugin->registerOpportunityMetadata($key, ['label' => $label, 'type' => 'multiselect', 'options' => $options]);
    }

    private static function registerText(Plugin $plugin, string $key, string $label): void
    {
        $plugin->registerOpportunityMetadata($key, ['label' => $label, 'type' => 'string']);
    }

    private static function registerJson(Plugin $plugin, string $key, string $label): void
    {
        $plugin->registerOpportunityMetadata($key, ['label' => $label, 'type' => 'json']);
    }

    // valor fixo => rótulo traduzido; em lista, o core faria do rótulo a chave gravada
    private static function options(array $cases): array
    {
        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    private static function targetingOptions(array $cases, TargetingField $field, bool $includesAllOptions = false): array
    {
        $synthetic = [TargetingOption::NOT_TARGETED->value => TargetingOption::NOT_TARGETED->label($field)];

        if ($includesAllOptions) {
            $synthetic[TargetingOption::ALL_OPTIONS->value] = TargetingOption::ALL_OPTIONS->label($field);
        }

        return $synthetic + self::options($cases);
    }
}
