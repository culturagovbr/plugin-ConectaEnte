<?php

namespace ConectaEnte\Http;

/**
 * Desfecho de `POST /api/v1/oportunidades/`: aceito, recusado (4xx), falho (200 sem JSON ou sem id)
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
        public readonly bool $failed = false,
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

    public static function failed(string $message): self
    {
        return new self(accepted: false, unreachable: false, message: $message, failed: true);
    }

    public static function unreachable(int $status = 0, ?string $transportError = null): self
    {
        return new self(accepted: false, unreachable: true, status: $status, transportError: $transportError);
    }

    /** O id do edital no CultBR, presente em todo envio aceito. */
    public function parEditalId(): ?int
    {
        return $this->accepted ? $this->response['id_par_edital'] : null;
    }
}
