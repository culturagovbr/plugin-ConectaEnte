<?php

namespace ConectaEnte\Dto;

/**
 * Parsing comum aos 4 níveis do PAR: só `id` é garantido pelo contrato, o resto vira `null` quando ausente.
 */
trait ParNodeFields
{
    private static function id(array $data): string
    {
        return (string) ($data['id'] ?? '');
    }

    private static function scalar(array $data, string $key): ?string
    {
        return isset($data[$key]) ? (string) $data[$key] : null;
    }

    /** @return array<int,mixed> */
    private static function children(array $data, string $key, callable $fromArray): array
    {
        return is_array($data[$key] ?? null) ? array_map($fromArray, $data[$key]) : [];
    }

    private function scalarFields(): array
    {
        return ['id' => $this->id, 'nome' => $this->nome, 'ano' => $this->ano, 'valor' => $this->valor];
    }
}
