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
    const SENDING = 'sending';
    const UNCERTAIN = 'uncertain';
    const SENT = 'sent';

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
     * O formulário aberto: pendente enquanto houver obrigatória sem resposta ou linha da planilha incompleta.
     *
     * @param array{answers: array, computed: array, suggested: array, missing: int[], stale: bool} $form
     */
    public static function withForm(array $structure, array $form, ?string $sendState = null, bool $sent = false): self
    {
        $complete = !$form['missing'] && empty($form['spreadsheetPending']);

        return new self($sendState ?? ($complete ? ($sent ? self::SENT : self::COMPLETE) : self::PENDING), structure: $structure, form: $form);
    }

    /** Se o formulário e a planilha estão completos, qualquer que seja a situação do envio. */
    public function isFormComplete(): bool
    {
        return $this->structure !== null && !$this->form['missing'] && empty($this->form['spreadsheetPending']);
    }

    /** Se o botão de envio aparece. */
    public function canSend(): bool
    {
        return in_array($this->state, [self::COMPLETE, self::SENT], true);
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
            'canSend' => $this->canSend(),
            'form' => $this->structure ? [
                'id' => $this->structure['id_formulario'] ?? null,
                'versionId' => $this->structure['id_formulario_versao'] ?? null,
                'version' => $this->structure['versao'] ?? null,
            ] : null,
            'structure' => $this->structure,
        ] + $this->form;
    }
}
