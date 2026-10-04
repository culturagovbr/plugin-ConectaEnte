<?php

namespace ConectaEnte\Http\Transport;

use ConectaEnte\Http\Response;

class CurlTransport implements TransportInterface
{
    // teto ditado pelo uso interativo deste par: a verificação de token responde na requisição do administrador
    const GET_CONNECT_TIMEOUT = 5;
    const GET_TIMEOUT = 10;

    // o PUT só roda na fila, onde ninguém espera: teto curto esgota as tentativas contra um CultBR que só demorou
    const PUT_CONNECT_TIMEOUT = 30;
    const PUT_TIMEOUT = 60;

    public function get(string $url, array $headers = []): Response
    {
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::GET_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::GET_TIMEOUT,
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
            CURLOPT_CONNECTTIMEOUT => self::PUT_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::PUT_TIMEOUT,
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
