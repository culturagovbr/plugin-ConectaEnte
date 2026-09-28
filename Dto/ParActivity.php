<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

final class ParActivity implements JsonSerializable
{
    use ParNodeFields;

    public function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly ?string $amount,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: self::id($data),
            name: self::scalar($data, 'nome'),
            amount: self::scalar($data, 'valor'),
        );
    }

    public function jsonSerialize(): array
    {
        return $this->scalarFields();
    }
}
