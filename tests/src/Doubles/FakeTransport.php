<?php

namespace Tests\ConectaEnte\Doubles;

use ConectaEnte\Http\Response;
use ConectaEnte\Http\Transport\TransportInterface;

/**
 * Devolve respostas preparadas e guarda o que foi pedido, sem sair do container.
 */
class FakeTransport implements TransportInterface
{
    public array $requestedUrls = [];
    public array $sentHeaders = [];
    public array $sentBodies = [];

    /** Um por requisição, alinhado com $requestedUrls e $sentHeaders; $sentBodies conta só as escritas.
     * @var string[] */
    public array $sentVerbs = [];

    public function __construct(private Response $response)
    {
    }

    public static function replying(int $status, array|string $body = []): self
    {
        return new self(Response::received($status, is_string($body) ? $body : json_encode($body)));
    }

    public static function unreachable(): self
    {
        return new self(Response::failed('Could not resolve host'));
    }

    public function get(string $url, array $headers = []): Response
    {
        $this->sentVerbs[] = 'GET';
        $this->requestedUrls[] = $url;
        $this->sentHeaders[] = $headers;

        return $this->response;
    }

    public function put(string $url, array $body, array $headers = []): Response
    {
        return $this->recorded('PUT', $url, $body, $headers);
    }

    public function post(string $url, array $body, array $headers = []): Response
    {
        return $this->recorded('POST', $url, $body, $headers);
    }

    private function recorded(string $verb, string $url, array $body, array $headers): Response
    {
        $this->sentVerbs[] = $verb;
        $this->requestedUrls[] = $url;
        $this->sentHeaders[] = $headers;
        $this->sentBodies[] = $body;

        return $this->response;
    }
}
