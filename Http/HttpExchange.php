<?php

namespace ConectaEnte\Http;

/**
 * Uma troca HTTP com o CultBR, como o histórico a registra.
 */
final class HttpExchange
{
    public function __construct(
        public readonly string $endpoint,
        public readonly string $method = 'POST',
        public readonly ?int $status = null,
        public readonly ?array $requestPayload = null,
        public readonly ?string $responseBody = null,
        public readonly ?array $responseHeaders = null,
        public readonly ?string $requestId = null,
        public readonly ?string $errorMessage = null,
        public readonly ?int $durationMs = null,
    ) {
    }

    /** Se a resposta veio com um corpo JSON, que é o que o cliente exige para aceitar um 200. */
    public function hasJsonBody(): bool
    {
        return $this->responseBody !== null && is_array(json_decode($this->responseBody, true));
    }
}
