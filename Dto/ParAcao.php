<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

final class ParAcao implements JsonSerializable
{
    use ParNodeFields;

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
        return new self(
            id: self::id($data),
            nome: self::scalar($data, 'nome'),
            ano: self::scalar($data, 'ano'),
            valor: self::scalar($data, 'valor'),
            atividades: self::children($data, 'atividades', [ParAtividade::class, 'fromArray']),
        );
    }

    public function jsonSerialize(): array
    {
        return [...$this->scalarFields(), 'atividades' => array_values($this->atividades)];
    }
}
