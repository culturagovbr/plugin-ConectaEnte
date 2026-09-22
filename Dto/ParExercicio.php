<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

final class ParExercicio implements JsonSerializable
{
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
        $metas = is_array($data['metas'] ?? null)
            ? array_map([ParMeta::class, 'fromArray'], $data['metas'])
            : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            nome: isset($data['nome']) ? (string) $data['nome'] : null,
            ano: isset($data['ano']) ? (string) $data['ano'] : null,
            valor: isset($data['valor']) ? (string) $data['valor'] : null,
            metas: $metas,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'ano' => $this->ano,
            'valor' => $this->valor,
            'metas' => array_values($this->metas),
        ];
    }
}
