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

    /** @param ParExercise[] $exercises */
    public function __construct(public readonly array $exercises)
    {
    }

    /**
     * A árvore do ente cujo cnpj casa; `data` é lista de entes, e `data[0]` não é o certo.
     */
    public static function fromApiListResponse(array $body, string $document): self
    {
        $targetDocument = self::onlyDigits($document);
        $data = $body['data'] ?? [];
        $items = array_filter(is_array($data) ? $data : [], 'is_array');
        $matches = array_values(array_filter(
            $items,
            fn($item) => self::onlyDigits((string) ($item['cnpj'] ?? '')) === $targetDocument,
        ));

        if (count($items) > count($matches)) {
            App::i()->log->warning(sprintf(
                'ParInformation: %d de %d entes em par-information não são o cnpj %s; descartados.',
                count($items) - count($matches),
                count($items),
                $targetDocument,
            ));
        }

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

        return new self(self::children($matches[0], 'exercicios', [ParExercise::class, 'fromArray']));
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
    public function isConsistentPath(string $exerciseId, string $goalId, string $actionId, string $activityId): bool
    {
        $exercise = $this->findById($this->exercises, $exerciseId);
        $goal = $exercise ? $this->findById($exercise->goals, $goalId) : null;
        $action = $goal ? $this->findById($goal->actions, $actionId) : null;
        $activity = $action ? $this->findById($action->activities, $activityId) : null;

        return $activity !== null;
    }

    /** @param array<ParExercise|ParGoal|ParAction|ParActivity> $items */
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
        return ['exercicios' => array_values($this->exercises)];
    }
}
