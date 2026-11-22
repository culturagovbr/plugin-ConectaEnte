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

    public function get(string $url, array $headers = [], bool $queued = false): Response
    {
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $queued ? self::QUEUED_CONNECT_TIMEOUT : self::INTERACTIVE_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => $queued ? self::QUEUED_TIMEOUT : self::INTERACTIVE_TIMEOUT,
            CURLOPT_HTTPHEADER => array_map(fn($name, $value) => "$name: $value", array_keys($headers), $headers),
        ]);

        return $this->execute($curl);
    }

    public function put(string $url, array $body, array $headers = []): Response
    {
        return $this->write('PUT', $url, $body, $headers);
    }

    public function post(string $url, array $body, array $headers = []): Response
    {
        return $this->write('POST', $url, $body, $headers);
    }

    // a API recebe `arquivos` repetido, e o array de campos do curl não repete nome: o corpo é montado aqui
    public function postMultipart(string $url, array $fields, array $files, array $headers = []): Response
    {
        $boundary = 'conectaente' . bin2hex(random_bytes(12));
        $body = '';

        foreach ($fields as [$name, $value]) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        }

        foreach ($files as $file) {
            $filename = str_replace('"', '', $file['filename']);
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$file['name']}\"; filename=\"{$filename}\"\r\nContent-Type: {$file['mime']}\r\n\r\n" . file_get_contents($file['path']) . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";
        $headers['Content-Type'] = "multipart/form-data; boundary={$boundary}";

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::QUEUED_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::QUEUED_TIMEOUT,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array_map(fn($name, $value) => "$name: $value", array_keys($headers), $headers),
        ]);

        return $this->execute($curl);
    }

    private function write(string $verb, string $url, array $body, array $headers): Response
    {
        $curl = curl_init($url);
        $headers['Content-Type'] = 'application/json';

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::QUEUED_CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::QUEUED_TIMEOUT,
            CURLOPT_CUSTOMREQUEST => $verb,
            CURLOPT_POSTFIELDS => JsonBody::encode($body),
            CURLOPT_HTTPHEADER => array_map(fn($name, $value) => "$name: $value", array_keys($headers), $headers),
        ]);

        return $this->execute($curl);
    }

    private function execute(\CurlHandle $curl): Response
    {
        $headers = [];
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($curl, $line) use (&$headers) {
            $parts = explode(':', $line, 2);

            if (count($parts) === 2) {
                $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            }

            return strlen($line);
        });

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $duration = (int) round(curl_getinfo($curl, CURLINFO_TOTAL_TIME) * 1000);

        curl_close($curl);

        if ($body === false || $error) {
            return Response::failed($error ?: 'falha de transporte', $duration);
        }

        return Response::received((int) $status, (string) $body, $headers, $duration);
    }
}
