<?php

namespace ConectaEnte\Dto;

use JsonSerializable;
use MapasCulturais\App;

/**
 * Árvore do PAR de um ente: Exercício -> Meta -> Ação -> Atividade; só `id` é garantido em cada nível.
 */
final class ParInformation implements JsonSerializable
{
    use ParNodeFields;

    /** @param ParExercicio[] $exercicios */
    public function __construct(public readonly array $exercicios)
    {
    }

    /**
     * A árvore do ente cujo cnpj casa; `data` é lista de entes, e `data[0]` não é o certo.
     */
    public static function fromApiListResponse(array $body, string $document): self
    {
        $targetDocument = self::onlyDigits($document);
        $matches = array_values(array_filter(
            $body['data'] ?? [],
            fn($item) => is_array($item) && self::onlyDigits((string) ($item['cnpj'] ?? '')) === $targetDocument,
        ));

        if (!$matches) {
            return self::empty();
        }

        if (count($matches) > 1) {
            App::i()->log->warning(sprintf(
                'ParInformation: %d entes casaram o cnpj %s em par-information; usando o primeiro.',
                count($matches),
                $targetDocument,
            ));
        }

        return new self(self::children($matches[0], 'exercicios', [ParExercicio::class, 'fromArray']));
    }

    public static function empty(): self
    {
        return new self([]);
    }

    private static function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    /**
     * Se a cadeia de ids é um caminho da árvore: cada nível filho do anterior.
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
}
