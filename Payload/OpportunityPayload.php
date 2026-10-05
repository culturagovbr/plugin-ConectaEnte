<?php

namespace ConectaEnte\Payload;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Services\PublicationStamp;
use ConectaEnte\Services\SealedOpportunity;
use ConectaEnte\Vocabulary\AffirmativeAction;
use ConectaEnte\Vocabulary\CulturalStage;
use ConectaEnte\Vocabulary\ExecutionType;
use ConectaEnte\Vocabulary\FundingSource;
use ConectaEnte\Vocabulary\LegalEntityType;
use ConectaEnte\Vocabulary\OpportunityStatus;
use ConectaEnte\Vocabulary\ProponentType;
use ConectaEnte\Vocabulary\Segment;
use ConectaEnte\Vocabulary\TargetingField;
use ConectaEnte\Vocabulary\TargetingOption;
use ConectaEnte\Vocabulary\ThematicAgenda;
use DateTimeInterface;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use RuntimeException;

/**
 * Monta o payload do edital que o CultBR recebe, a partir da oportunidade selada.
 */
final class OpportunityPayload
{
    const DETAIL_MAX_LENGTH = 600;

    public function __construct(
        private SealedOpportunity $sealedOpportunity,
        private PublicationStamp $publicationStamp,
    ) {
    }

    /**
     * O payload da oportunidade selada; dado que falta sai nulo, para o envio nomeá-lo.
     */
    public function build(Opportunity $opportunity): array
    {
        $federativeEntity = $this->sealedOpportunity->federativeEntityOf($opportunity);

        if (!$federativeEntity) {
            throw new RuntimeException("A oportunidade {$opportunity->id} não é um edital selado por Ente Federado.");
        }

        // na ordem do ParEditalInMapas
        $payload = [
            'id' => $opportunity->id,
            'id_exercicio' => $this->parId($opportunity, CultBrMetadata::PAR_EXERCISE_ID),
            'id_meta' => $this->parId($opportunity, CultBrMetadata::PAR_GOAL_ID),
            'id_acao' => $this->parId($opportunity, CultBrMetadata::PAR_ACTION_ID),
            'id_atividade' => $this->parId($opportunity, CultBrMetadata::PAR_ACTIVITY_ID),
            'numero_e_titulo_edital' => $opportunity->name ?: null,
            'forma_de_execucao' => $this->executionType($opportunity),
            'status' => $this->status($opportunity),
            'data_publicacao_edital' => $this->date($this->publicationStamp->publicationDate($opportunity)),
            'detalhamento_objeto' => $this->objectDetail($opportunity),
            'numero_previsto_vagas' => $opportunity->vacancies === null ? null : (int) $opportunity->vacancies,
            'valor_total_edital' => $this->decimal($opportunity->totalResource),
            'data_inicial_prazo_inscricao' => $this->date($opportunity->registrationFrom),
            'data_final_prazo_inscricao' => $this->date($opportunity->registrationTo),
            'tipos_proponentes' => $this->proponentTypes($opportunity),
            'segmentos_artistico_culturais' => $this->targeting($opportunity, TargetingField::SEGMENT),
            'segmento_artistico_cultural_especificar' => $this->otherSpecification($opportunity, CultBrMetadata::SEGMENTS, CultBrMetadata::SEGMENTS_OTHER, Segment::OTHER->value),
            'etapas_fazer_cultural' => $this->targeting($opportunity, TargetingField::CULTURAL_STAGE),
            'etapa_fazer_cultural_especificar' => $this->otherSpecification($opportunity, CultBrMetadata::CULTURAL_STAGES, CultBrMetadata::CULTURAL_STAGES_OTHER, CulturalStage::OTHER->value),
            'pautas_especificas' => $this->targeting($opportunity, TargetingField::THEMATIC_AGENDA),
            'pauta_especifica_especificar' => $this->otherSpecification($opportunity, CultBrMetadata::THEMATIC_AGENDAS, CultBrMetadata::THEMATIC_AGENDAS_OTHER, ThematicAgenda::OTHER->value),
            'categorias_edital' => $opportunity->registrationRanges ?: [],
            'recursos_territorios_prioritarios' => $this->targeting($opportunity, TargetingField::PRIORITY_TERRITORY),
            'links_da_pagina_pnab' => $this->links($opportunity),
            'pdf_edital' => $opportunity->getFile('rules')?->url,
            'recursos_outras_fontes' => $this->fundingSources($opportunity),
            'tipos_formas_inscricao' => $this->registrationChannels($opportunity),
            'reserva_vagas_cotas' => $this->quotaReservation($opportunity),
            'outras_modalidades_acoes_afirmativas' => $this->affirmativeActions($opportunity),
            'ente_federado' => ['cnpj' => $federativeEntity->document, 'nome' => $federativeEntity->name],
        ];

        App::i()->applyHook('conectaente.opportunityPayload', [$opportunity, &$payload]);

        return $payload;
    }

    /** O contrato tipa os quatro ids do PAR como inteiros; o metadado guarda string. */
    private function parId(Opportunity $opportunity, string $key): ?int
    {
        $value = (string) $opportunity->$key;

        return ctype_digit($value) ? (int) $value : null;
    }

    private function status(Opportunity $opportunity): array
    {
        $status = OpportunityStatus::tryFrom((int) $opportunity->status);

        if (!$status) {
            throw new RuntimeException("O status {$opportunity->status} da oportunidade {$opportunity->id} não tem correspondente no CultBR.");
        }

        return $status->toPayload();
    }

    private function executionType(Opportunity $opportunity): ?string
    {
        $value = $opportunity->{CultBrMetadata::EXECUTION_TYPE};

        return $value ? ExecutionType::tryFrom($value)?->text() : null;
    }

    /**
     * A data em RFC 3339, no fuso da instalação — o core grava os metadados de data sem fuso.
     */
    private function date(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : null;
    }

    private function objectDetail(Opportunity $opportunity): ?string
    {
        $detail = trim((string) ($opportunity->longDescription ?: $opportunity->shortDescription));

        return $detail === '' ? null : mb_substr($detail, 0, self::DETAIL_MAX_LENGTH);
    }

    private function decimal(mixed $value): ?string
    {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }

    /**
     * @return string[] os tipos do contrato; "Pessoa Jurídica" rende um por variante escolhida
     */
    private function proponentTypes(Opportunity $opportunity): array
    {
        $types = [];

        foreach ($opportunity->registrationProponentTypes as $label) {
            if (ProponentType::isLegalEntityLabel($label)) {
                foreach ($opportunity->{CultBrMetadata::LEGAL_ENTITY_TYPES} as $legalEntityType) {
                    $types[] = LegalEntityType::tryFrom($legalEntityType)?->proponentType()->value;
                }

                continue;
            }

            $types[] = ProponentType::tryFromLabel($label)?->value;
        }

        return array_values(array_unique(array_filter($types)));
    }

    /**
     * Os rótulos selecionados, unidos por vírgula, como o CultEditais os envia.
     */
    private function targeting(Opportunity $opportunity, TargetingField $field): ?string
    {
        $vocabulary = $field->vocabulary();
        $values = $opportunity->{$field->metadataKey()} ?: [];

        if ($values === [TargetingOption::NOT_TARGETED->value]) {
            return $field->notTargetedText();
        }

        if (in_array(TargetingOption::ALL_OPTIONS->value, $values, true)) {
            return implode(', ', array_map(fn($option) => $option->text(), $vocabulary::cases()));
        }

        $labels = [];

        foreach ($values as $value) {
            if ($value === TargetingOption::NOT_TARGETED->value) {
                $labels[] = $field->notTargetedText();

                continue;
            }

            // valor fora do vocabulário vai cru, como no CultEditais
            $labels[] = $vocabulary::tryFrom($value)?->text() ?? $value;
        }

        return $labels ? implode(', ', $labels) : null;
    }

    /**
     * O que especificar em "Outros": vazio sem a opção marcada, nulo quando marcada e sem texto.
     */
    private function otherSpecification(Opportunity $opportunity, string $key, string $otherKey, string $otherValue): ?string
    {
        if (!in_array($otherValue, $opportunity->$key ?: [], true)) {
            return '';
        }

        $specification = trim((string) $opportunity->$otherKey);

        return $specification === '' ? null : $specification;
    }

    /**
     * As fontes de recurso em snake_case; o `_id` das linhas livres é da tela e não vai ao payload.
     */
    private function fundingSources(Opportunity $opportunity): array
    {
        $block = $this->jsonBlock($opportunity, CultBrMetadata::FUNDING_SOURCES);
        $sources = [
            'houve_utilizacao' => $block['houveUtilizacao'] ?? null,
            'recursos_proprios' => $this->decimal($block[FundingSource::OWN_RESOURCES->value] ?? null),
            'convenios_parcerias' => $this->decimal($block[FundingSource::FEDERATIVE_AGREEMENTS->value] ?? null),
            'emendas_parlamentares' => $this->decimal($block[FundingSource::PARLIAMENTARY_AMENDMENTS->value] ?? null),
            'remanescentes_ciclo_1' => $this->decimal($block[FundingSource::FIRST_CYCLE_REMAINDER->value] ?? null),
            'outras_fontes' => [],
        ];

        foreach ($block[FundingSource::OTHER_SOURCES->value] ?? [] as $source) {
            $sources['outras_fontes'][] = [
                'nome_fonte' => $source['nomeFonte'] ?? '',
                'valor' => $this->decimal($source['valor'] ?? null) ?? '0.00',
            ];
        }

        return $sources;
    }

    /** @return array<int, array{tipo: string, descricao: string}> */
    private function registrationChannels(Opportunity $opportunity): array
    {
        $block = $this->jsonBlock($opportunity, CultBrMetadata::REGISTRATION_CHANNELS);

        if (($block['previstasNoEdital'] ?? null) !== 'sim') {
            return [];
        }

        return array_values(array_map(
            fn(array $channel) => ['tipo' => $channel['tipo'] ?? '', 'descricao' => $channel['descricao'] ?? ''],
            array_filter($block['formas'] ?? [], 'is_array'),
        ));
    }

    /** @return array<int, array{label: string, vagas: int, valor_destinado: string, nao_aplicavel: bool}> */
    private function quotaReservation(Opportunity $opportunity): array
    {
        return array_values(array_map(fn(array $quota) => [
            'label' => trim((string) ($quota['label'] ?? '')),
            'vagas' => (int) ($quota['vagas'] ?? 0),
            'valor_destinado' => $this->decimal($quota['valorDestinado'] ?? null) ?? '0.00',
            'nao_aplicavel' => (bool) ($quota['naoAplicavel'] ?? false),
        ], array_filter($this->jsonBlock($opportunity, CultBrMetadata::QUOTA_RESERVATION), 'is_array')));
    }

    private function affirmativeActions(Opportunity $opportunity): array
    {
        $block = $this->jsonBlock($opportunity, CultBrMetadata::AFFIRMATIVE_ACTIONS);
        $actions = [
            'opcoes' => array_values(array_filter($block['opcoes'] ?? [], 'is_string')),
            'outra_legislacao_descricao' => (string) ($block['outra_legislacao_descricao'] ?? ''),
        ];

        foreach (AffirmativeAction::cases() as $action) {
            if ($action->hasGroups()) {
                $actions[$action->value] = array_values(array_filter($block[$action->value] ?? [], 'is_string'));
            }
        }

        return $actions;
    }

    /** O metadado json volta como stdClass; convertido, lê-se como array em qualquer nível. */
    private function jsonBlock(Opportunity $opportunity, string $key): array
    {
        $block = json_decode(json_encode($opportunity->$key), true);

        return is_array($block) ? $block : [];
    }

    /** @return array<int, array{url: string, label: ?string}> */
    private function links(Opportunity $opportunity): array
    {
        return array_map(
            fn($link) => ['url' => $link->value, 'label' => $link->title],
            $opportunity->getMetaLists('links') ?: [],
        );
    }
}
