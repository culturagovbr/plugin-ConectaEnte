<?php

namespace ConectaEnte\Http\Transport;

use ConectaEnte\Http\Response;

/**
 * Executa a requisição à Conecta Ente. Servidor que não respondeu devolve resposta de
 * indisponibilidade (Response::failed), não exceção; o corpo das escritas vai como JSON.
 */
interface TransportInterface
{
    public function get($url, array $headers = []);

    public function put($url, array $body, array $headers = []);

    public function post($url, array $body, array $headers = []);
}
