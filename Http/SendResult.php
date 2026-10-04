<?php

namespace ConectaEnte\Http;

/**
 * Desfecho de `PUT /api/v1/oportunidades/{id}`: aceito, recusado sem retentativa (4xx),
 * ou indisponível (5xx/falha de conexão) — este último é o único retentável.
 */
final class SendResult
{
    private function __construct(
        public readonly bool $accepted,
        public readonly bool $unreachable,
        public readonly ?array $response = null,
        public readonly ?string $message = null,
        public readonly int $status = 0,
        public readonly ?string $transportError = null,
    ) {
    }

    public static function ok(array $response): self
    {
        return new self(accepted: true, unreachable: false, response: $response);
    }

    public static function rejected(string $message): self
    {
        return new self(accepted: false, unreachable: false, message: $message);
    }

    public static function unreachable(int $status = 0, ?string $transportError = null): self
    {
        return new self(accepted: false, unreachable: true, status: $status, transportError: $transportError);
    }
}
