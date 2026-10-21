<?php

namespace Tests\ConectaEnte\Doubles;

use ConectaEnte\Http\Response;
use ConectaEnte\Http\Transport\TransportInterface;

class FakeTransport implements TransportInterface
{
    public $requestedUrls = [];
    public $sentHeaders = [];
    public $sentBodies = [];
    public $sentVerbs = [];

    private $response;

    public function __construct(Response $response)
    {
        $this->response = $response;
    }

    public static function replying($status, $body = [], array $headers = [], $durationMs = null)
    {
        return new self(Response::received($status, is_string($body) ? $body : json_encode($body), $headers, $durationMs));
    }

    public static function unreachable()
    {
        return new self(Response::failed('Could not resolve host'));
    }

    public function get($url, array $headers = [])
    {
        $this->sentVerbs[] = 'GET';
        $this->requestedUrls[] = $url;
        $this->sentHeaders[] = $headers;

        return $this->response;
    }

    public function put($url, array $body, array $headers = [])
    {
        return $this->recorded('PUT', $url, $body, $headers);
    }

    public function post($url, array $body, array $headers = [])
    {
        return $this->recorded('POST', $url, $body, $headers);
    }

    private function recorded($verb, $url, array $body, array $headers)
    {
        $this->sentVerbs[] = $verb;
        $this->requestedUrls[] = $url;
        $this->sentHeaders[] = $headers;
        $this->sentBodies[] = $body;

        return $this->response;
    }
}
