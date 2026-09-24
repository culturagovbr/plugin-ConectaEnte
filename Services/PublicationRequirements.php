<?php

namespace ConectaEnte\Services;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Vocabulary\LegalEntityType;
use ConectaEnte\Vocabulary\ProponentType;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\i;

final class PublicationRequirements
{
    const RANGE_VALUE_TOLERANCE = 0.009;

    public function __construct(private PublicationStamp $publicationStamp)
    {
    }

    /**
     * O que falta para publicar a oportunidade selada: campo => mensagens.
     */
    public function missing(Opportunity $opportunity): array
    {
        return array_merge_recursive(
            $this->amountErrors($opportunity),
            $this->proponentTypeErrors($opportunity),
            $this->legalEntityErrors($opportunity),
            $this->rulesErrors($opportunity),
            $this->rangeErrors($opportunity),
            $this->publishedAtErrors($opportunity),
            $this->registrationEndErrors($opportunity),
        );
    }

    private function amountErrors(Opportunity $opportunity): array
    {
        $errors = [];

        if (!$this->isPositiveNumber($opportunity->vacancies)) {
            $errors['vacancies'] = [i::__('O campo "Total de vagas" é obrigatório e deve ser maior que zero.')];
        }

        if (!$this->isPositiveNumber($opportunity->totalResource)) {
            $errors['totalResource'] = [i::__('O campo "Valor total" é obrigatório e deve ser maior que zero.')];
        }

        return $errors;
    }

    private function proponentTypeErrors(Opportunity $opportunity): array
    {
        $proponentTypes = $opportunity->registrationProponentTypes;

        if (!$proponentTypes) {
            return ['registrationProponentTypes' => [i::__('O campo "Tipos do proponente" é obrigatório.')]];
        }

        $messages = [];

        foreach ($proponentTypes as $label) {
            if (!ProponentType::tryFromLabel($label) && !ProponentType::isLegalEntityLabel($label)) {
                $messages[] = sprintf(i::__('O tipo de proponente "%s" não tem correspondente no CultBR.'), $label);
            }
        }

        return $messages ? ['registrationProponentTypes' => $messages] : [];
    }

    private function legalEntityErrors(Opportunity $opportunity): array
    {
        $hasLegalEntity = array_filter($opportunity->registrationProponentTypes, ProponentType::isLegalEntityLabel(...));

        if (!$hasLegalEntity) {
            return [];
        }

        $values = $opportunity->{CultBrMetadata::LEGAL_ENTITY_TYPES};
        $messages = [];

        if (!array_filter($values, LegalEntityType::tryFrom(...))) {
            $messages[] = i::__('O campo "Tipo de pessoa jurídica" é obrigatório.');
        }

        foreach ($values as $value) {
            if (!LegalEntityType::tryFrom($value)) {
                $messages[] = sprintf(i::__('O tipo de pessoa jurídica "%s" não tem correspondente no CultBR.'), $value);
            }
        }

        return $messages ? [CultBrMetadata::LEGAL_ENTITY_TYPES => $messages] : [];
    }

    private function rulesErrors(Opportunity $opportunity): array
    {
        return $opportunity->getFile('rules') ? [] : ['rules' => [i::__('O campo "Adicionar regulamento" é obrigatório.')]];
    }

    // soma comparada só com o total informado; o total ausente já é erro próprio
    private function rangeErrors(Opportunity $opportunity): array
    {
        $ranges = $opportunity->registrationRanges;

        if (!$ranges) {
            return [];
        }

        $vacancies = $opportunity->vacancies;
        $totalResource = $opportunity->totalResource;
        $errors = [];

        if ($vacancies !== null && $this->rangeSum($ranges, 'limit', 'intval') !== (int) $vacancies) {
            $errors['registrationRangesVacancies'] = [i::__('O total de vagas das categorias deve ser igual ao Total de vagas definido;')];
        }

        if ($totalResource !== null && abs($this->rangeSum($ranges, 'value', 'floatval') - (float) $totalResource) > self::RANGE_VALUE_TOLERANCE) {
            $errors['registrationRangesTotalResource'] = [i::__('O total em valores das categorias deve ser igual ao Valor total definido;')];
        }

        return $errors;
    }

    private function rangeSum(array $ranges, string $column, callable $cast): int|float
    {
        return array_sum(array_map($cast, array_column($ranges, $column)));
    }

    // pelo status atual, o PATCH {status: 1} cobraria a data antes de o carimbo gravá-la
    private function publishedAtErrors(Opportunity $opportunity): array
    {
        if (!$this->publicationStamp->wasPublished($opportunity) || $this->publicationStamp->hasPublicationDate($opportunity)) {
            return [];
        }

        return [CultBrMetadata::PUBLISHED_AT => [i::__('O campo "Data de publicação do edital" é obrigatório.')]];
    }

    // a data nula o core já cobra; a fictícia do fluxo contínuo passa na regra dele e não é prazo
    private function registrationEndErrors(Opportunity $opportunity): array
    {
        $registrationTo = $opportunity->registrationTo;

        if (!$registrationTo) {
            return [];
        }

        if ($opportunity->isContinuousFlow && !$opportunity->hasEndDate) {
            return ['registrationTo' => [i::__('A data final das inscrições é obrigatória: use "Definir data final das inscrições".')]];
        }

        if ($registrationTo->format('Y-m-d H:i') === Opportunity::CONTINUOUS_FLOW_DATE) {
            return ['registrationTo' => [i::__('A data final das inscrições não pode ser 01/01/2111, a data que o fluxo contínuo usa quando não há data final.')]];
        }

        return [];
    }

    private function isPositiveNumber(mixed $value): bool
    {
        return is_numeric($value) && (float) $value > 0;
    }
}
