<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

final class ParExercicio implements JsonSerializable
{
    use ParNodeFields;

    /** @param ParMeta[] $metas */
    public function __construct(
        public readonly string $id,
        public readonly ?string $nome,
        public readonly ?string $ano,
        public readonly ?string $valor,
        public readonly array $metas,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: self::id($data),
            nome: self::scalar($data, 'nome'),
            ano: self::scalar($data, 'ano'),
            valor: self::scalar($data, 'valor'),
            metas: self::children($data, 'metas', [ParMeta::class, 'fromArray']),
        );
    }

    public function jsonSerialize(): array
    {
        return [...$this->scalarFields(), 'metas' => array_values($this->metas)];
    }
}
