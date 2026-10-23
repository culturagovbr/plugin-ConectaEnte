<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

final class ParGoal implements JsonSerializable
{
    use ParNodeFields;

    /** @param ParAction[] $actions */
    public function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly ?string $amount,
        public readonly array $actions,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: self::id($data),
            name: self::scalar($data, 'nome'),
            amount: self::scalar($data, 'valor'),
            actions: self::children($data, 'acoes', [ParAction::class, 'fromArray']),
        );
    }

    public function jsonSerialize(): array
    {
        return [...$this->scalarFields(), 'acoes' => array_values($this->actions)];
    }
}
