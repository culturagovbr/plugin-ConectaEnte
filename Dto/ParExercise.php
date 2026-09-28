<?php

namespace ConectaEnte\Dto;

use JsonSerializable;

final class ParExercise implements JsonSerializable
{
    use ParNodeFields;

    /** @param ParGoal[] $goals */
    public function __construct(
        public readonly string $id,
        public readonly ?string $year,
        public readonly array $goals,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: self::id($data),
            year: self::scalar($data, 'ano'),
            goals: self::children($data, 'metas', [ParGoal::class, 'fromArray']),
        );
    }

    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'ano' => $this->year, 'metas' => array_values($this->goals)];
    }
}
