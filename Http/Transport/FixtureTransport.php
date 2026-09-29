<?php

namespace ConectaEnte\Http\Transport;

use ConectaEnte\Http\Response;
use MapasCulturais\App;

/**
 * Em modo de desenvolvimento, a resposta vem de `fixtures/<rota>.json` em vez da API.
 */
class FixtureTransport implements TransportInterface
{
    public function __construct(private string $directory)
    {
    }

    public function get(string $url, array $headers = []): Response
    {
        $route = $this->routeOf($url);
        $file = "{$this->directory}/{$route}.json";

        if (!is_readable($file)) {
            App::i()->log->warning("ConectaEnte em modo dev: sem fixture para a rota {$route} em {$file}");

            return Response::failed("sem fixture para a rota {$route}");
        }

        return Response::received(200, (string) file_get_contents($file));
    }

    /**
     * O envio real nunca deveria chamar o transporte em modo dev — quem decide isso é
     * `OpportunitySender`, que grava o desfecho `simulated` sem passar por aqui.
     */
    public function put(string $url, array $body, array $headers = []): Response
    {
        App::i()->log->warning("ConectaEnte em modo dev: put() não deveria ser chamado, rota {$this->routeOf($url)}");

        return Response::failed('envio real não roda em modo simulado');
    }

    // o último segmento nomeia a rota: /api/v1/par-information vira par-information
    private function routeOf(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return basename($path) ?: 'index';
    }
}
