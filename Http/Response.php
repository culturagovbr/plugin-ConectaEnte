<?php

namespace ConectaEnte\Http;

/**
 * Resposta HTTP crua: o status e o corpo, ou a falha de transporte quando não houve resposta.
 */
final class Response
{
    private function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly ?string $transportError = null,
    ) {
    }

    public static function received(int $status, string $body): self
    {
        return new self($status, $body);
    }

    public static function failed(string $error): self
    {
        return new self(0, '', $error);
    }

    public function reachedServer(): bool
    {
        return $this->transportError === null;
    }

    /**
     * Corpo decodificado, ou array vazio quando não é JSON.
     */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
