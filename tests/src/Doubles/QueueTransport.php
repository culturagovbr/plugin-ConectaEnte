<?php

namespace Tests\ConectaEnte\Doubles;

use ConectaEnte\Http\Response;
use ConectaEnte\Http\Transport\TransportInterface;

/**
 * Devolve uma resposta preparada por chamada, na ordem; um Throwable na fila é lançado.
 */
class QueueTransport implements TransportInterface
{
    public array $requestedUrls = [];

    /** @param array<Response|\Throwable> $queue */
    public function __construct(private array $queue)
    {
    }

    public static function replying(Response|\Throwable ...$queue): self
    {
        return new self($queue);
    }

    public function get(string $url, array $headers = []): Response
    {
        $this->requestedUrls[] = $url;
        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next ?? Response::failed('fila de respostas vazia');
    }
}
