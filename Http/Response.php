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
        public readonly array $headers = [],
        public readonly ?int $durationMs = null,
    ) {
    }

    public static function received(int $status, string $body, array $headers = [], ?int $durationMs = null): self
    {
        return new self($status, $body, null, $headers, $durationMs);
    }

    public static function failed(string $error, ?int $durationMs = null): self
    {
        return new self(0, '', $error, [], $durationMs);
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
        return $this->decoded() ?? [];
    }

    /**
     * Corpo decodificado, ou nulo quando não é JSON — distingue o corpo imprestável do JSON vazio.
     */
    public function decoded(): ?array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
