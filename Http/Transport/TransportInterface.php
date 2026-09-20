<?php

namespace ConectaEnte\Http\Transport;

use ConectaEnte\Http\Response;

interface TransportInterface
{
    /**
     * Executa a requisição e devolve a resposta, ou uma resposta de indisponibilidade
     * quando o servidor não respondeu.
     *
     * @param array<string,string> $headers
     */
    public function get(string $url, array $headers = []): Response;
}
