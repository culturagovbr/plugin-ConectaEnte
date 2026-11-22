<?php

namespace ConectaEnte\Services;

use JsonSerializable;

/**
 * Em que ponto está o resultado do edital, do ponto de vista de quem o preenche.
 */
final class ResultState implements JsonSerializable
{
    const NO_TAB = 'noTab';
    const UNAVAILABLE = 'unavailable';
    const NO_PAR_EDITAL = 'noParEdital';
    const FEDERATIVE_ENTITY_CHANGED = 'federativeEntityChanged';
    const AWAITING_FINAL_PUBLICATION = 'awaitingFinalPublication';
    const NO_FORM = 'noForm';
    const CULTBR_UNAVAILABLE = 'cultbrUnavailable';
    const PENDING = 'pending';
    const COMPLETE = 'complete';

    private function __construct(
        public readonly string $state,
        public readonly ?string $reason = null,
        public readonly ?array $structure = null,
        public readonly array $form = [],
    ) {
    }

    public static function of(string $state, ?string $reason = null): self
    {
        return new self($state, $reason);
    }

    /**
     * O formulário aberto: pendente enquanto houver obrigatória sem resposta.
     *
     * @param array{answers: array, computed: array, suggested: array, missing: int[], stale: bool} $form
     */
    public static function withForm(array $structure, array $form): self
    {
        return new self($form['missing'] ? self::PENDING : self::COMPLETE, structure: $structure, form: $form);
    }

    /** Se a aba de resultado aparece para esta oportunidade. */
    public function hasTab(): bool
    {
        return $this->state !== self::NO_TAB;
    }

    public function jsonSerialize(): array
    {
        return [
            'state' => $this->state,
            'reason' => $this->reason,
            'form' => $this->structure ? [
                'id' => $this->structure['id_formulario'] ?? null,
                'versionId' => $this->structure['id_formulario_versao'] ?? null,
                'version' => $this->structure['versao'] ?? null,
            ] : null,
            'structure' => $this->structure,
        ] + $this->form;
    }
}
