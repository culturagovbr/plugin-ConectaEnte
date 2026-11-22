<?php

namespace Tests\ConectaEnte\Doubles;

use ConectaEnte\Http\Response;
use ConectaEnte\Http\Transport\TransportInterface;

/**
 * Responde pelo fim da rota, uma resposta por chamada; a última da fila se repete, e um callable roda antes de responder.
 */
class RoutedTransport implements TransportInterface
{
    public array $posts = [];

    /** @param array<string, list<Response|callable>> $routes */
    public function __construct(private array $routes)
    {
    }

    public static function json(int $status, array $body): Response
    {
        return Response::received($status, json_encode($body));
    }

    public function get(string $url, array $headers = [], bool $queued = false): Response
    {
        return $this->reply($url);
    }

    public function put(string $url, array $body, array $headers = []): Response
    {
        return $this->reply($url);
    }

    public function post(string $url, array $body, array $headers = []): Response
    {
        $this->posts[] = ['url' => $url, 'body' => $body];

        return $this->reply($url, 'POST ');
    }

    public function postMultipart(string $url, array $fields, array $files, array $headers = []): Response
    {
        $this->posts[] = ['url' => $url, 'fields' => $fields, 'files' => $files];

        return $this->reply($url, 'POST ');
    }

    private function reply(string $url, string $verb = ''): Response
    {
        $path = parse_url($url, PHP_URL_PATH);

        foreach ($this->routes as $route => &$queue) {
            if (str_starts_with($route, $verb ?: 'GET ') && str_ends_with($path, substr($route, strpos($route, ' ') + 1))) {
                $next = count($queue) > 1 ? array_shift($queue) : $queue[0];

                return is_callable($next) ? $next() : $next;
            }
        }

        return Response::failed("rota sem resposta preparada: {$url}");
    }
}
