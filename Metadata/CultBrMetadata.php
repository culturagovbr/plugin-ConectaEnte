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
use DateTime;
use InvalidArgumentException;
use MapasCulturais\i;

final class CultBrMetadata
{
    /** O que separa um campo do plugin de um campo do core na lista de pendências. */
    const PREFIX = 'conectaente_';

    const EXECUTION_TYPE = 'conectaente_executionType';
    const SEGMENTS = 'conectaente_segments';
    const SEGMENTS_OTHER = 'conectaente_segmentsOther';
    const CULTURAL_STAGES = 'conectaente_culturalStages';
    const CULTURAL_STAGES_OTHER = 'conectaente_culturalStagesOther';
    const THEMATIC_AGENDAS = 'conectaente_thematicAgendas';
    const THEMATIC_AGENDAS_OTHER = 'conectaente_thematicAgendasOther';
    const PRIORITY_TERRITORIES = 'conectaente_priorityTerritories';
    const FUNDING_SOURCES = 'conectaente_fundingSources';
    const QUOTA_RESERVATION = 'conectaente_quotaReservation';
    const REGISTRATION_CHANNELS = 'conectaente_registrationChannels';
    const AFFIRMATIVE_ACTIONS = 'conectaente_affirmativeActions';
    const LEGAL_ENTITY_TYPES = 'conectaente_legalEntityTypes';
    const PUBLISHED_AT = 'conectaente_publishedAt';

    const PAR_EXERCISE_ID = 'conectaente_parExercicioId';
    const PAR_GOAL_ID = 'conectaente_parMetaId';
    const PAR_ACTION_ID = 'conectaente_parAcaoId';
    const PAR_ACTIVITY_ID = 'conectaente_parAtividadeId';

    const PAR_KEYS = [self::PAR_EXERCISE_ID, self::PAR_GOAL_ID, self::PAR_ACTION_ID, self::PAR_ACTIVITY_ID];

    // o desfecho da última tentativa de envio; nenhuma aba lê estas chaves ainda
    const SEND_STATUS = 'conectaente_sendStatus';
    const SEND_REASON = 'conectaente_sendReason';
    const SEND_AT = 'conectaente_sendAt';

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
            // o Entity.js só converte em data o tipo 'datetime'; o core só tem conversores para 'DateTime'
            'type' => 'datetime',
            'serialize' => self::serializeDateTime(...),
            'unserialize' => fn($value) => $value ? new DateTime($value) : $value,
        ]);

        self::registerText($plugin, self::PAR_EXERCISE_ID, i::__('Exercício do PAR'));
        self::registerText($plugin, self::PAR_GOAL_ID, i::__('Meta do PAR'));
        self::registerText($plugin, self::PAR_ACTION_ID, i::__('Ação do PAR'));
        self::registerText($plugin, self::PAR_ACTIVITY_ID, i::__('Atividade do PAR'));

        self::registerText($plugin, self::SEND_STATUS, i::__('Situação do envio ao CultBR'));
        self::registerText($plugin, self::SEND_REASON, i::__('Motivo da situação do envio ao CultBR'));
        self::registerText($plugin, self::SEND_AT, i::__('Data da última tentativa de envio ao CultBR'));
    }

    private static function serializeDateTime(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        if (is_string($value)) {
            $value = new DateTime($value);
        }

        if (!$value instanceof DateTime) {
            throw new InvalidArgumentException('value must be a DateTime or a date time string');
        }

        return $value->format('Y-m-d H:i:s');
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
