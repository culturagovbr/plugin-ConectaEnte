<?php

namespace ConectaEnte\Http\Transport;

use ConectaEnte\Http\Response;

class CurlTransport implements TransportInterface
{
    // qualquer leitura pode estar rodando com alguém esperando na tela, inclusive a árvore do PAR
    const INTERACTIVE_CONNECT_TIMEOUT = 5;
    const INTERACTIVE_TIMEOUT = 10;

    // escrever só acontece na fila: teto curto esgota as tentativas contra um CultBR que só demorou
    const QUEUED_CONNECT_TIMEOUT = 30;
    const QUEUED_TIMEOUT = 60;

    public function get(string $url, array $headers = []): Response
    {
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::INTERACTIVE_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::INTERACTIVE_TIMEOUT,
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
            CURLOPT_CONNECTTIMEOUT => self::QUEUED_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::QUEUED_TIMEOUT,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => JsonBody::encode($body),
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
