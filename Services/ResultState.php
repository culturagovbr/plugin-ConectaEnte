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
    const AVAILABLE = 'available';

    private function __construct(
        public readonly string $state,
        public readonly ?string $reason = null,
        public readonly ?array $structure = null,
    ) {
    }

    public static function of(string $state, ?string $reason = null): self
    {
        return new self($state, $reason);
    }

    public static function available(array $structure): self
    {
        return new self(self::AVAILABLE, structure: $structure);
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
        ];
    }
}
