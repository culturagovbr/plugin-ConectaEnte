<?php

namespace ConectaEnte\Services;

/**
 * Desfecho de um envio, no vocabulário que o plugin grava — no metadado da oportunidade e no histórico.
 */
final class SendOutcome
{
    /** Estados do envelope do histórico: nem a tentativa nem o metadado da oportunidade gravam estes dois. */
    const PENDING = 'pending';
    const ABANDONED = 'abandoned';

    const SUCCESS = 'success';
    const SIMULATED = 'simulated';
    const REJECTED = 'rejected';
    const ERROR = 'error';

    private function __construct(
        public readonly string $status,
        public readonly ?string $reason = null,
        private readonly bool $retryable = false,
    ) {
    }

    public static function success(): self
    {
        return new self(self::SUCCESS);
    }

    public static function simulated(): self
    {
        return new self(self::SIMULATED);
    }

    public static function rejected(string $reason): self
    {
        return new self(self::REJECTED, $reason);
    }

    /** Falha definitiva: tentativas esgotadas, ou exceção no caminho do envio. */
    public static function error(string $reason): self
    {
        return new self(self::ERROR, $reason);
    }

    /** Vale repetir, então não é gravado ainda; o motivo sobrevive para o registro do esgotamento. */
    public static function unavailable(string $reason): self
    {
        return new self(self::ERROR, $reason, retryable: true);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
