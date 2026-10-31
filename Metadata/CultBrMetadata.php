<?php

namespace ConectaEnte\Metadata;

use ConectaEnte\Services\FundingSourceName;
use ConectaEnte\Vocabulary\CulturalStage;
use ConectaEnte\Vocabulary\ExecutionType;
use ConectaEnte\Vocabulary\PriorityTerritory;
use ConectaEnte\Vocabulary\Segment;
use ConectaEnte\Vocabulary\TargetingField;
use ConectaEnte\Vocabulary\TargetingOption;
use ConectaEnte\Vocabulary\ThematicAgenda;
use MapasCulturais\i;

/**
 * Metadados da oportunidade que o CultBR exige e o core do Mapas v5 não tem (#45). No CultEditais
 * quem os registra é o tema Pnab; aqui o plugin registra sozinho. Chaves, rótulos e opções seguem a
 * `develop` (v7) do plugin, para os dados serem os mesmos nas duas versões.
 *
 * Este arquivo cobre os campos de escolha com vocabulário e seus pares "especificar". Os blocos JSON
 * (recursos de outras fontes, reserva de vagas, formas de inscrição, ações afirmativas) ficam à parte.
 */
class CultBrMetadata
{
    /** O que separa um campo do plugin de um campo do core. */
    const PREFIX = 'conectaente_';

    const EXECUTION_TYPE = 'conectaente_executionType';
    const SEGMENTS = 'conectaente_segments';
    const SEGMENTS_OTHER = 'conectaente_segmentsOther';
    const CULTURAL_STAGES = 'conectaente_culturalStages';
    const CULTURAL_STAGES_OTHER = 'conectaente_culturalStagesOther';
    const THEMATIC_AGENDAS = 'conectaente_thematicAgendas';
    const THEMATIC_AGENDAS_OTHER = 'conectaente_thematicAgendasOther';
    const PRIORITY_TERRITORIES = 'conectaente_priorityTerritories';

    // blocos JSON: o formato de cada um está nas constantes dos vocabulários e no README
    const FUNDING_SOURCES = 'conectaente_fundingSources';
    const QUOTA_RESERVATION = 'conectaente_quotaReservation';
    const REGISTRATION_CHANNELS = 'conectaente_registrationChannels';
    const AFFIRMATIVE_ACTIONS = 'conectaente_affirmativeActions';

    /** O multiselect do core guarda os valores num texto único separado por este caractere. */
    const SEPARATOR = ';';

    /**
     * Campo principal → [campo "especificar", valor que o exige].
     *
     * @return array[]
     */
    private static function specifications()
    {
        return [
            self::SEGMENTS => [self::SEGMENTS_OTHER, Segment::OTHER],
            self::CULTURAL_STAGES => [self::CULTURAL_STAGES_OTHER, CulturalStage::OTHER],
            self::THEMATIC_AGENDAS => [self::THEMATIC_AGENDAS_OTHER, ThematicAgenda::OTHER],
        ];
    }

    /**
     * Rótulo do campo principal e do seu "especificar".
     *
     * @return string[]
     */
    private static function labels()
    {
        return [
            self::EXECUTION_TYPE => 'Tipo de Edital',
            self::SEGMENTS => 'Segmento artístico-cultural',
            self::SEGMENTS_OTHER => 'Especificar segmento artístico-cultural',
            self::CULTURAL_STAGES => 'Etapa do fazer cultural',
            self::CULTURAL_STAGES_OTHER => 'Especificar etapa do fazer cultural',
            self::THEMATIC_AGENDAS => 'Pauta temática',
            self::THEMATIC_AGENDAS_OTHER => 'Especificar pauta temática',
            self::PRIORITY_TERRITORIES => 'Território',
            self::FUNDING_SOURCES => 'Houve utilização de recursos de outras fontes?',
            self::QUOTA_RESERVATION => 'Reserva de vagas (cotas)',
            self::REGISTRATION_CHANNELS => 'Formas de inscrição previstas no edital',
            self::AFFIRMATIVE_ACTIONS => 'Outras modalidades de ações afirmativas',
        ];
    }

    /**
     * Chave → configuração aceita por `registerOpportunityMetadata`.
     *
     * @return array[]
     */
    public static function definitions()
    {
        $labels = self::labels();

        $definitions = [
            self::EXECUTION_TYPE => [
                'label' => self::t($labels[self::EXECUTION_TYPE]),
                'type' => 'select',
                'options' => ExecutionType::options(),
            ],
        ];

        foreach (TargetingField::cases() as $field) {
            $key = TargetingField::metadataKey($field);

            $definitions[$key] = [
                'label' => self::t($labels[$key]),
                'type' => 'multiselect',
                // só o segmento tem "Todas as opções"
                'options' => self::targetingOptions($field, $field === TargetingField::SEGMENT),
            ];

            if (isset(self::specifications()[$key])) {
                $other = self::specifications()[$key][0];
                $definitions[$other] = ['label' => self::t($labels[$other]), 'type' => 'string'];
            }
        }

        foreach (self::jsonBlocks() as $key => $sanitize) {
            $definitions[$key] = [
                'label' => self::t($labels[$key]),
                'type' => 'json',
                'serialize' => function ($value) use ($sanitize) {
                    return JsonBlock::serialize($value, $sanitize);
                },
                'unserialize' => function ($value) {
                    return JsonBlock::unserialize($value);
                },
            ];
        }

        return $definitions;
    }

    /**
     * Os blocos JSON → o saneamento aplicado ao gravar (nulo quando não há).
     *
     * @return (callable|null)[]
     */
    public static function jsonBlocks()
    {
        return [
            self::FUNDING_SOURCES => [new FundingSourceName(), 'sanitizeBlock'],
            self::QUOTA_RESERVATION => null,
            self::REGISTRATION_CHANNELS => null,
            self::AFFIRMATIVE_ACTIONS => null,
        ];
    }

    /**
     * @return string[]
     */
    public static function keys()
    {
        return array_keys(self::definitions());
    }

    /**
     * @param object $registrar quem tem `registerOpportunityMetadata` (o plugin)
     */
    public static function register($registrar)
    {
        foreach (self::definitions() as $key => $definition) {
            $registrar->registerOpportunityMetadata($key, $definition);
        }
    }

    /**
     * Valores escolhidos, de um texto "a;b" (como o core guarda) ou de uma lista.
     *
     * @param mixed $value
     *
     * @return string[]
     */
    public static function values($value)
    {
        if (is_array($value)) {
            $list = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $list = explode(self::SEPARATOR, $value);
        } else {
            return [];
        }

        $list = array_map(function ($item) {
            return is_scalar($item) ? trim((string) $item) : '';
        }, $list);

        return array_values(array_filter($list, function ($item) {
            return $item !== '';
        }));
    }

    /**
     * O que vai ao payload: os valores num texto só, "a, b", nunca a chave crua nem a lista. As opções
     * sintéticas viram o texto do campo (`TargetingOption::text`).
     *
     * @param mixed       $value
     * @param string|null $metadataKey chave do campo, para traduzir as opções sintéticas
     *
     * @return string
     */
    public static function toText($value, $metadataKey = null)
    {
        $field = $metadataKey === null ? null : TargetingField::fromMetadataKey($metadataKey);

        return implode(', ', array_map(function ($item) use ($field) {
            return $field !== null && TargetingOption::isSynthetic($item) ? TargetingOption::text($item, $field) : $item;
        }, self::values($value)));
    }

    /**
     * Campos "especificar" que o preenchimento exige e estão vazios: o principal tem a opção
     * "Outros / Outra (especificar)" marcada e o detalhamento não foi escrito. A regra só é definida
     * aqui; barrar a publicação é a #46.
     *
     * @param array $values chave do metadado → valor
     *
     * @return string[] chaves dos campos "especificar" faltantes
     */
    public static function missingSpecifications(array $values)
    {
        $missing = [];

        foreach (self::specifications() as $main => $specification) {
            list($specifyKey, $trigger) = $specification;

            $chosen = isset($values[$main]) ? self::values($values[$main]) : [];
            $detail = isset($values[$specifyKey]) && is_scalar($values[$specifyKey]) ? trim((string) $values[$specifyKey]) : '';

            if (in_array($trigger, $chosen, true) && $detail === '') {
                $missing[] = $specifyKey;
            }
        }

        return $missing;
    }

    /**
     * Opções sintéticas primeiro, depois as do vocabulário; valor fixo → rótulo traduzido (em lista, o
     * core faria do rótulo a chave gravada).
     *
     * @param string $field            um valor de TargetingField
     * @param bool   $includesAllOptions se entra também "Todas as opções"
     *
     * @return string[]
     */
    private static function targetingOptions($field, $includesAllOptions)
    {
        $options = [TargetingOption::NOT_TARGETED => TargetingOption::label(TargetingOption::NOT_TARGETED, $field)];

        if ($includesAllOptions) {
            $options[TargetingOption::ALL_OPTIONS] = TargetingOption::label(TargetingOption::ALL_OPTIONS, $field);
        }

        $vocabulary = TargetingField::vocabulary($field);

        return $options + $vocabulary::options();
    }

    private static function t($text)
    {
        return class_exists(i::class) ? i::__($text) : $text;
    }
}
