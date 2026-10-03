<?php

namespace ConectaEnte\Services;

/**
 * Desfecho de uma tentativa de envio, no vocabulário gravado como metadado.
 */
final class SendOutcome
{
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

    /** Vale repetir, então não é gravado ainda; esgotadas as tentativas, o que sobra é `error`. */
    public static function unavailable(): self
    {
        return new self(self::ERROR, retryable: true);
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
