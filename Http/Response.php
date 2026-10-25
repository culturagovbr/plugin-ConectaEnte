<?php

namespace ConectaEnte\Http;

class Response
{
    private $status;
    private $body;
    private $transportError;
    private $headers;
    private $durationMs;

    private function __construct($status, $body, $transportError = null, array $headers = [], $durationMs = null)
    {
        $this->status = $status;
        $this->body = $body;
        $this->transportError = $transportError;
        $this->headers = $headers;
        $this->durationMs = $durationMs;
    }

    public static function received($status, $body, array $headers = [], $durationMs = null)
    {
        return new self((int) $status, (string) $body, null, $headers, $durationMs);
    }

    public static function failed($error, $durationMs = null)
    {
        return new self(0, '', (string) $error, [], $durationMs);
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function getTransportError()
    {
        return $this->transportError;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getDurationMs()
    {
        return $this->durationMs;
    }

    public function reachedServer()
    {
        return $this->transportError === null;
    }

    public function json()
    {
        $decoded = $this->decoded();

        return $decoded === null ? [] : $decoded;
    }

    // null (e não []) para o corpo que não é JSON: json() e decoded() precisam poder distinguir os dois
    public function decoded()
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
