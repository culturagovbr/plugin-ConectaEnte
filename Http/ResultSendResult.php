<?php

namespace ConectaEnte\Http;

/**
 * Desfecho de `POST /oportunidades/{id}/resultado`: aceito (201), recusado (4xx) ou sem resposta (5xx e transporte).
 */
final class ResultSendResult
{
    private function __construct(
        public readonly bool $accepted,
        public readonly bool $unreachable,
        public readonly int $status = 0,
        public readonly ?array $response = null,
        public readonly ?ApiErrors $errors = null,
        public readonly ?string $message = null,
    ) {
    }

    public static function accepted(?array $response): self
    {
        return new self(accepted: true, unreachable: false, status: 201, response: $response);
    }

    public static function rejected(int $status, ApiErrors $errors, string $message): self
    {
        return new self(accepted: false, unreachable: false, status: $status, errors: $errors, message: $message);
    }

    public static function unreachable(int $status = 0, ?string $message = null): self
    {
        return new self(accepted: false, unreachable: true, status: $status, message: $message);
    }

    /** O id do resultado criado, quando o 201 o traz inteiro. */
    public function resultId(): ?int
    {
        $id = $this->response['id_par_edital_resultado'] ?? null;

        return is_int($id) && $id > 0 ? $id : null;
    }
}
