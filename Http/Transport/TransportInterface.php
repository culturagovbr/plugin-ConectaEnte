<?php

namespace ConectaEnte\Http\Transport;

use ConectaEnte\Http\Response;

/**
 * Executa a requisição ao CultBR. Servidor que não respondeu devolve resposta de
 * indisponibilidade, não exceção; o corpo das escritas vai como JSON.
 */
interface TransportInterface
{
    public function get(string $url, array $headers = [], bool $queued = false): Response;

    /** Atualiza o recurso da url. */
    public function put(string $url, array $body, array $headers = []): Response;

    /** Cria na coleção da url, ou atualiza quando o corpo traz um id que o CultBR já conhece. */
    public function post(string $url, array $body, array $headers = []): Response;

    /**
     * @param list<array{0: string, 1: string}> $fields
     * @param list<array{name: string, path: string, filename: string, mime: string}> $files
     */
    public function postMultipart(string $url, array $fields, array $files, array $headers = []): Response;
}
