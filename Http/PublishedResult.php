<?php

namespace ConectaEnte\Http;

/**
 * O resultado publicado de um edital no CultBR: existe, não existe, ou não foi possível saber.
 */
final class PublishedResult
{
    const NOT_FOUND_DETAIL = 'Resultado publicado não encontrado';

    private function __construct(
        public readonly ?int $resultId,
        public readonly bool $known,
        public readonly ?array $body = null,
        public readonly ?string $reason = null,
    ) {
    }

    public static function published(int $resultId, array $body): self
    {
        return new self(resultId: $resultId, known: true, body: $body);
    }

    public static function none(): self
    {
        return new self(resultId: null, known: true);
    }

    public static function unknown(string $reason): self
    {
        return new self(resultId: null, known: false, reason: $reason);
    }

    /** Se há resultado publicado; só faz sentido quando a leitura foi determinada. */
    public function exists(): bool
    {
        return $this->resultId !== null;
    }
}
