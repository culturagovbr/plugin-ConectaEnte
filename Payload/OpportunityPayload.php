<?php

namespace ConectaEnte\Payload;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Services\SealedOpportunity;
use ConectaEnte\Vocabulary\ExecutionType;
use ConectaEnte\Vocabulary\LegalEntityType;
use ConectaEnte\Vocabulary\OpportunityStatus;
use ConectaEnte\Vocabulary\ProponentType;
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

    public function __construct(private SealedOpportunity $sealedOpportunity)
    {
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

        $payload = [
            'id' => $opportunity->id,
            'numero_e_titulo_edital' => $opportunity->name ?: null,
            'forma_de_execucao' => $this->executionType($opportunity),
            'status' => $this->status($opportunity),
            'data_publicacao_edital' => $this->date($opportunity->{CultBrMetadata::PUBLISHED_AT}),
            'data_inicial_prazo_inscricao' => $this->date($opportunity->registrationFrom),
            'data_final_prazo_inscricao' => $this->date($opportunity->registrationTo),
            'detalhamento_objeto' => $this->objectDetail($opportunity),
            'numero_previsto_vagas' => $opportunity->vacancies === null ? null : (int) $opportunity->vacancies,
            'valor_total_edital' => $this->decimal($opportunity->totalResource),
            'tipos_proponentes' => $this->proponentTypes($opportunity),
            'categorias_edital' => $opportunity->registrationRanges ?: [],
            'links_da_pagina_pnab' => $this->links($opportunity),
            'pdf_edital' => $opportunity->getFile('rules')?->url,
            'ente_federado' => ['cnpj' => $federativeEntity->document, 'nome' => $federativeEntity->name],
        ];

        App::i()->applyHook('conectaente.opportunityPayload', [$opportunity, &$payload]);

        return $payload;
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

    /** @return array<int, array{url: string, label: ?string}> */
    private function links(Opportunity $opportunity): array
    {
        return array_map(
            fn($link) => ['url' => $link->value, 'label' => $link->title],
            $opportunity->getMetaLists('links') ?: [],
        );
    }
}
