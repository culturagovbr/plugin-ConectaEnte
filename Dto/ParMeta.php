<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

final class ParMeta implements JsonSerializable
{
    use ParNodeFields;

    /** @param ParAcao[] $acoes */
    public function __construct(
        public readonly string $id,
        public readonly ?string $nome,
        public readonly ?string $ano,
        public readonly ?string $valor,
        public readonly array $acoes,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: self::id($data),
            nome: self::scalar($data, 'nome'),
            ano: self::scalar($data, 'ano'),
            valor: self::scalar($data, 'valor'),
            acoes: self::children($data, 'acoes', [ParAcao::class, 'fromArray']),
        );
    }

    public function jsonSerialize(): array
    {
        return [...$this->scalarFields(), 'acoes' => array_values($this->acoes)];
    }
}
