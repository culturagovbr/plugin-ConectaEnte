<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

final class ParAtividade implements JsonSerializable
{
    use ParNodeFields;

    public function __construct(
        public readonly string $id,
        public readonly ?string $nome,
        public readonly ?string $ano,
        public readonly ?string $valor,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: self::id($data),
            nome: self::scalar($data, 'nome'),
            ano: self::scalar($data, 'ano'),
            valor: self::scalar($data, 'valor'),
        );
    }

    public function jsonSerialize(): array
    {
        return $this->scalarFields();
    }
}
