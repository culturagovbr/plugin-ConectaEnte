<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

final class ParAtividade implements JsonSerializable
{
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
            id: (string) ($data['id'] ?? ''),
            nome: isset($data['nome']) ? (string) $data['nome'] : null,
            ano: isset($data['ano']) ? (string) $data['ano'] : null,
            valor: isset($data['valor']) ? (string) $data['valor'] : null,
        );
    }

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'nome' => $this->nome, 'ano' => $this->ano, 'valor' => $this->valor];
    }
}
