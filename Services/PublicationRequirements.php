<?php

namespace ConectaEnte\Services;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Vocabulary\AffirmativeAction;
use ConectaEnte\Vocabulary\AffirmativeActionGroup;
use ConectaEnte\Vocabulary\CulturalStage;
use ConectaEnte\Vocabulary\FundingSource;
use ConectaEnte\Vocabulary\LegalEntityType;
use ConectaEnte\Vocabulary\ProponentType;
use ConectaEnte\Vocabulary\RegistrationChannel;
use ConectaEnte\Vocabulary\Segment;
use ConectaEnte\Vocabulary\ThematicAgenda;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\i;
use Respect\Validation\Validator;

final class PublicationRequirements
{
    const RANGE_VALUE_TOLERANCE = 0.009;
    const REGISTRATION_CHANNELS_EMAIL = CultBrMetadata::REGISTRATION_CHANNELS . 'Email';
    const OTHER_LEGISLATION_MAX_LENGTH = 140;
    const YES = 'sim';
    const NO = 'nao';

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
            $this->executionTypeErrors($opportunity),
            $this->targetingErrors($opportunity),
            $this->otherSpecificationErrors($opportunity),
            $this->fundingSourceErrors($opportunity),
            $this->registrationChannelErrors($opportunity),
            $this->affirmativeActionErrors($opportunity),
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
        return $this->keyed('registrationProponentTypes', $this->choiceMessages(
            $opportunity->registrationProponentTypes,
            fn($label) => ProponentType::tryFromLabel($label) || ProponentType::isLegalEntityLabel($label),
            i::__('O campo "Tipos do proponente" é obrigatório.'),
            fn($label) => sprintf(i::__('O tipo de proponente "%s" não tem correspondente no CultBR.'), $label),
        ));
    }

    private function legalEntityErrors(Opportunity $opportunity): array
    {
        if (!array_filter($opportunity->registrationProponentTypes, ProponentType::isLegalEntityLabel(...))) {
            return [];
        }

        return $this->keyed(CultBrMetadata::LEGAL_ENTITY_TYPES, $this->choiceMessages(
            $opportunity->{CultBrMetadata::LEGAL_ENTITY_TYPES},
            LegalEntityType::tryFrom(...),
            i::__('O campo "Tipo de pessoa jurídica" é obrigatório.'),
            fn($value) => sprintf(i::__('O tipo de pessoa jurídica "%s" não tem correspondente no CultBR.'), $value),
        ));
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

    private function executionTypeErrors(Opportunity $opportunity): array
    {
        $value = $opportunity->{CultBrMetadata::EXECUTION_TYPE};

        return $this->registeredChoiceErrors($opportunity, CultBrMetadata::EXECUTION_TYPE, $value ? [$value] : []);
    }

    private function targetingErrors(Opportunity $opportunity): array
    {
        $errors = [];

        foreach ([CultBrMetadata::SEGMENTS, CultBrMetadata::CULTURAL_STAGES, CultBrMetadata::THEMATIC_AGENDAS, CultBrMetadata::PRIORITY_TERRITORIES] as $key) {
            $errors += $this->registeredChoiceErrors($opportunity, $key, $opportunity->$key);
        }

        return $errors;
    }

    private function otherSpecificationErrors(Opportunity $opportunity): array
    {
        $specifications = [
            CultBrMetadata::SEGMENTS_OTHER => [CultBrMetadata::SEGMENTS, Segment::OTHER->value, i::__('O campo especificar segmento artístico-cultural é obrigatório quando "Outros (especificar)" é selecionado.')],
            CultBrMetadata::CULTURAL_STAGES_OTHER => [CultBrMetadata::CULTURAL_STAGES, CulturalStage::OTHER->value, i::__('O campo especificar etapa do fazer cultural é obrigatório quando "Outra (especificar)" é selecionada.')],
            CultBrMetadata::THEMATIC_AGENDAS_OTHER => [CultBrMetadata::THEMATIC_AGENDAS, ThematicAgenda::OTHER->value, i::__('O campo especificar pauta temática é obrigatório quando "Outra (especificar)" é selecionada.')],
        ];
        $errors = [];

        foreach ($specifications as $otherKey => [$key, $otherValue, $message]) {
            if (in_array($otherValue, $opportunity->$key, true) && (string) $opportunity->$otherKey === '') {
                $errors[$otherKey] = [$message];
            }
        }

        return $errors;
    }

    private function fundingSourceErrors(Opportunity $opportunity): array
    {
        $block = $this->jsonBlock($opportunity, CultBrMetadata::FUNDING_SOURCES);
        $answer = $block['houveUtilizacao'] ?? null;

        if ($answer === self::NO) {
            return [];
        }

        if ($answer !== self::YES) {
            return [CultBrMetadata::FUNDING_SOURCES => [i::__('O campo "Houve utilização de recursos de outras fontes?" é obrigatório.')]];
        }

        $otherSources = array_filter((array) ($block[FundingSource::OTHER_SOURCES->value] ?? []), 'is_array');
        $hasAmountSource = array_filter(FundingSource::cases(), fn($source) => $source !== FundingSource::OTHER_SOURCES && ($block[$source->value] ?? null) !== null);

        if (!$hasAmountSource && !$otherSources) {
            return [CultBrMetadata::FUNDING_SOURCES => [i::__('Selecione pelo menos uma fonte de recurso para continuar.')]];
        }

        if ($otherSources && !array_filter($otherSources, fn($source) => trim((string) ($source['nomeFonte'] ?? '')) !== '')) {
            return [CultBrMetadata::FUNDING_SOURCES => [i::__('Preencha o nome de pelo menos uma fonte em "Recursos de outras fontes".')]];
        }

        return [];
    }

    private function registrationChannelErrors(Opportunity $opportunity): array
    {
        $block = $this->jsonBlock($opportunity, CultBrMetadata::REGISTRATION_CHANNELS);
        $answer = $block['previstasNoEdital'] ?? null;

        if ($answer === self::NO) {
            return [];
        }

        if ($answer !== self::YES) {
            return [CultBrMetadata::REGISTRATION_CHANNELS => [i::__('O campo "Formas de inscrição previstas no edital" é obrigatório.')]];
        }

        $channels = array_filter((array) ($block['formas'] ?? []), 'is_array');

        if (!$channels) {
            return [CultBrMetadata::REGISTRATION_CHANNELS => [i::__('Selecione pelo menos uma forma de inscrição para continuar.')]];
        }

        $messages = [];
        $hasInvalidEmail = false;

        foreach ($channels as $channel) {
            $type = (string) ($channel['tipo'] ?? '');
            $description = trim((string) ($channel['descricao'] ?? ''));

            if ($type === '') {
                $messages[] = i::__('Escolha o tipo de cada forma de inscrição marcada.');
            } elseif (!RegistrationChannel::tryFrom($type)) {
                $messages[] = sprintf(i::__('A forma de inscrição "%s" não tem correspondente no CultBR.'), $type);
            }

            if ($description === '') {
                $messages[] = i::__('Preencha a descrição de cada forma de inscrição marcada.');
            } elseif ($type === RegistrationChannel::EMAIL->value && !Validator::email()->validate($description)) {
                $hasInvalidEmail = true;
            }
        }

        return $this->keyed(CultBrMetadata::REGISTRATION_CHANNELS, $messages)
            + ($hasInvalidEmail ? [self::REGISTRATION_CHANNELS_EMAIL => [i::__('Informe um e-mail válido.')]] : []);
    }

    private function affirmativeActionErrors(Opportunity $opportunity): array
    {
        $block = $this->jsonBlock($opportunity, CultBrMetadata::AFFIRMATIVE_ACTIONS);
        $options = $this->strings($block['opcoes'] ?? []);

        $messages = $this->choiceMessages(
            $options,
            AffirmativeAction::tryFrom(...),
            i::__('Selecione pelo menos uma opção.'),
            fn($option) => sprintf(i::__('A ação afirmativa "%s" não tem correspondente no CultBR.'), $option),
        );

        foreach (array_filter(array_map(AffirmativeAction::tryFrom(...), $options)) as $action) {
            if ($action->hasGroups()) {
                array_push($messages, ...$this->choiceMessages(
                    $this->strings($block[$action->value] ?? []),
                    AffirmativeActionGroup::tryFrom(...),
                    i::__('Por favor, selecione pelo menos uma subcategoria.'),
                    fn($group) => sprintf(i::__('O grupo "%s" não tem correspondente no CultBR.'), $group),
                ));
            }
        }

        if (in_array(AffirmativeAction::OTHER_LEGISLATION->value, $options, true)) {
            array_push($messages, ...$this->otherLegislationMessages($block['outra_legislacao_descricao'] ?? null));
        }

        return $this->keyed(CultBrMetadata::AFFIRMATIVE_ACTIONS, $messages);
    }

    private function otherLegislationMessages(mixed $description): array
    {
        $description = is_string($description) ? trim($description) : '';

        if ($description === '') {
            return [i::__('Por favor, preencha a descrição.')];
        }

        if (mb_strlen($description) > self::OTHER_LEGISLATION_MAX_LENGTH) {
            return [sprintf(i::__('A descrição da outra ação afirmativa deve ter no máximo %d caracteres.'), self::OTHER_LEGISLATION_MAX_LENGTH)];
        }

        return [];
    }

    private function registeredChoiceErrors(Opportunity $opportunity, string $key, array $values): array
    {
        $definition = $opportunity->getRegisteredMetadata($key);
        $label = mb_strtolower($definition->label);

        return $this->keyed($key, $this->choiceMessages(
            $values,
            fn($value) => array_key_exists($value, $definition->options),
            sprintf(i::__('O campo %s é obrigatório.'), $label),
            fn($value) => sprintf(i::__('A opção "%s" de %s não tem correspondente no CultBR.'), $value, $label),
        ));
    }

    // valor fora do vocabulário é nomeado e não conta como resposta
    private function choiceMessages(array $values, callable $isKnown, string $requiredMessage, callable $unknownMessage): array
    {
        $messages = array_filter($values, $isKnown) ? [] : [$requiredMessage];

        foreach ($values as $value) {
            if (!$isKnown($value)) {
                $messages[] = $unknownMessage($value);
            }
        }

        return $messages;
    }

    private function keyed(string $key, array $messages): array
    {
        return $messages ? [$key => array_values(array_unique($messages))] : [];
    }

    // o json do metadado volta como stdClass; convertido, lê-se como array em qualquer nível
    private function jsonBlock(Opportunity $opportunity, string $key): array
    {
        $block = json_decode(json_encode($opportunity->$key), true);

        return is_array($block) ? $block : [];
    }

    private function strings(mixed $values): array
    {
        return array_values(array_filter((array) $values, 'is_string'));
    }

    private function isPositiveNumber(mixed $value): bool
    {
        return is_numeric($value) && (float) $value > 0;
    }
}
