<?php

namespace ConectaEnte\Http\Transport;

use ConectaEnte\Http\Response;

class CurlTransport implements TransportInterface
{
    /**
     * Tempos curtos de propósito: a verificação roda no caminho de uma requisição do
     * administrador, e API lenta não pode virar tela travada.
     */
    const CONNECT_TIMEOUT = 5;
    const TIMEOUT = 10;

    public function get(string $url, array $headers = []): Response
    {
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER => array_map(fn($name, $value) => "$name: $value", array_keys($headers), $headers),
        ]);

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($body === false || $error) {
            return Response::failed($error ?: 'falha de transporte');
        }

        return Response::received((int) $status, (string) $body);
    }

    public function put(string $url, array $body, array $headers = []): Response
    {
        $curl = curl_init($url);
        $headers['Content-Type'] = 'application/json';

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            // json_encode devolve false em silêncio com UTF-8 inválido/NAN/INF: sem a flag, o corpo
            // sairia vazio e a API recusaria com 422, mascarando um bug local como recusa do CultBR
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => array_map(fn($name, $value) => "$name: $value", array_keys($headers), $headers),
        ]);

        $responseBody = curl_exec($curl);
        $error = curl_error($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($responseBody === false || $error) {
            return Response::failed($error ?: 'falha de transporte');
        }

        return Response::received((int) $status, (string) $responseBody);
    }
}
