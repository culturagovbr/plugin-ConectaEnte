<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

final class ParAction implements JsonSerializable
{
    use ParNodeFields;

    /** @param ParActivity[] $activities */
    public function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly ?string $amount,
        public readonly array $activities,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: self::id($data),
            name: self::scalar($data, 'nome'),
            amount: self::scalar($data, 'valor'),
            activities: self::children($data, 'atividades', [ParActivity::class, 'fromArray']),
        );
    }

    public function jsonSerialize(): array
    {
        return [...$this->scalarFields(), 'atividades' => array_values($this->activities)];
    }
}
