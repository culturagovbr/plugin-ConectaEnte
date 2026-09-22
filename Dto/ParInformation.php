<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

/**
 * Árvore do PAR de um ente: Exercício -> Meta -> Ação -> Atividade. Só `id` é
 * garantido em cada nível pelo contrato (`ParInformationEnteSchema`); o resto
 * vira `null` quando ausente, em vez de estourar.
 */
final class ParInformation implements JsonSerializable
{
    /** @param ParExercicio[] $exercicios */
    public function __construct(public readonly array $exercicios)
    {
    }

    public static function fromApiResponse(array $body): self
    {
        $exercicios = array_map([ParExercicio::class, 'fromArray'], self::listOf($body, 'exercicios'));

        return new self($exercicios);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Confere se a cadeia de ids forma um caminho válido na árvore: cada nível
     * precisa ser filho do anterior. Não decide se a seleção é obrigatória —
     * só que, se presente, é consistente.
     */
    public function isConsistentPath(string $exercicioId, string $metaId, string $acaoId, string $atividadeId): bool
    {
        $exercicio = $this->findById($this->exercicios, $exercicioId);
        $meta = $exercicio ? $this->findById($exercicio->metas, $metaId) : null;
        $acao = $meta ? $this->findById($meta->acoes, $acaoId) : null;
        $atividade = $acao ? $this->findById($acao->atividades, $atividadeId) : null;

        return $atividade !== null;
    }

    /** @param array<ParExercicio|ParMeta|ParAcao|ParAtividade> $items */
    private function findById(array $items, string $id): mixed
    {
        foreach ($items as $item) {
            if ((string) $item->id === $id) {
                return $item;
            }
        }

        return null;
    }

    public function jsonSerialize(): array
    {
        return ['exercicios' => array_values($this->exercicios)];
    }

    /** @return array<array-key,mixed> */
    private static function listOf(array $body, string $key): array
    {
        $value = $body[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
