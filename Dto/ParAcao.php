<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

final class ParAcao implements JsonSerializable
{
    /** @param ParAtividade[] $atividades */
    public function __construct(
        public readonly string $id,
        public readonly ?string $nome,
        public readonly ?string $ano,
        public readonly ?string $valor,
        public readonly array $atividades,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $atividades = is_array($data['atividades'] ?? null)
            ? array_map([ParAtividade::class, 'fromArray'], $data['atividades'])
            : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            nome: isset($data['nome']) ? (string) $data['nome'] : null,
            ano: isset($data['ano']) ? (string) $data['ano'] : null,
            valor: isset($data['valor']) ? (string) $data['valor'] : null,
            atividades: $atividades,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'ano' => $this->ano,
            'valor' => $this->valor,
            'atividades' => array_values($this->atividades),
        ];
    }
}
