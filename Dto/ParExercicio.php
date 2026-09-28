<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

final class ParExercicio implements JsonSerializable
{
    use ParNodeFields;

    /** @param ParMeta[] $metas */
    public function __construct(
        public readonly string $id,
        public readonly ?string $ano,
        public readonly array $metas,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: self::id($data),
            ano: self::scalar($data, 'ano'),
            metas: self::children($data, 'metas', [ParMeta::class, 'fromArray']),
        );
    }

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'ano' => $this->ano, 'metas' => array_values($this->metas)];
    }
}
