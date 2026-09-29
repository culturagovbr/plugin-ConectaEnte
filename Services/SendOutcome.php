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
    const UNREACHABLE = 'unreachable';

    private function __construct(
        public readonly string $status,
        public readonly ?string $reason = null,
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

    public static function unreachable(): self
    {
        return new self(self::UNREACHABLE);
    }

    public function isRetryable(): bool
    {
        return $this->status === self::UNREACHABLE;
    }
}
