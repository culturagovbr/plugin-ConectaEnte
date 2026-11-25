<?php

namespace ConectaEnte\Http;

/**
 * Desfecho de uma leitura do resultado: corpo lido, recurso inexistente no formato do CultBR, recusa ou silêncio.
 */
final class ApiRead
{
    private function __construct(
        public readonly ?array $body,
        public readonly bool $notFound = false,
        public readonly bool $unreachable = false,
        public readonly ?string $message = null,
    ) {
    }

    public static function ok(array $body): self
    {
        return new self(body: $body);
    }

    public static function notFound(string $message): self
    {
        return new self(body: null, notFound: true, message: $message);
    }

    public static function rejected(string $message): self
    {
        return new self(body: null, message: $message);
    }

    public static function unreachable(?string $message = null): self
    {
        return new self(body: null, unreachable: true, message: $message);
    }
}
