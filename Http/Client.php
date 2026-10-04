<?php

namespace ConectaEnte\Http;

use ConectaEnte\Dto\ParInformation;
use ConectaEnte\Http\Transport\CurlTransport;
use ConectaEnte\Http\Transport\TransportInterface;
use MapasCulturais\i;

class Client
{
    const TYPE_SYSTEM = 'SISTEMA';

    private TransportInterface $transport;

    public function __construct(
        private string $host,
        ?TransportInterface $transport = null,
        // em modo dev a fixture é uma só, e precisa servir a qualquer ente cadastrado
        private bool $acceptsAnyEnte = false,
    ) {
        $this->transport = $transport ?? new CurlTransport;
    }

    /**
     * Verifica o token contra a API e descobre de qual ente ele é.
     */
    public function validateToken(string $token): TokenValidation
    {
        $response = $this->transport->get($this->url('/api/v1/integracao/validar-token'), ['token' => $token]);

        if (!$response->reachedServer()) {
            return TokenValidation::unreachable();
        }

        $body = $response->json();

        if ($response->status === 200) {
            return $this->readValidation($body);
        }

        if ($response->status >= 500) {
            return TokenValidation::unreachable();
        }

        return TokenValidation::reject($this->readDetail($body, $response->status, i::__('A Plataforma CultBR recusou a verificação do token (HTTP %d).')));
    }

    /**
     * Responde sem token: separa "API fora do ar" de "credencial rejeitada".
     */
    public function isHealthy(): bool
    {
        return $this->transport->get($this->url('/health'))->status === 200;
    }

    /**
     * A árvore do PAR do ente dono do token, casada pelo cnpj na lista que a resposta devolve.
     */
    public function getParInformation(string $token, string $document): ParInformationResult
    {
        $response = $this->transport->get($this->url('/api/v1/par-information'), ['token' => $token]);

        if (!$response->reachedServer()) {
            return ParInformationResult::unreachable();
        }

        if ($response->status === 200) {
            $body = $response->decoded();

            // corpo imprestável não pode virar árvore vazia: o job gravaria "ente sem PAR" no cache
            if ($body === null) {
                return ParInformationResult::unreachable(i::__('A resposta da API não é um JSON válido.'));
            }

            return ParInformationResult::ok(ParInformation::fromApiListResponse($body, $this->enteDocument($body, $document)));
        }

        if ($response->status === 404) {
            return ParInformationResult::notFound();
        }

        if ($response->status >= 500) {
            return ParInformationResult::unreachable();
        }

        return ParInformationResult::rejected($this->readDetail($response->json(), $response->status, i::__('A Plataforma CultBR recusou a consulta ao Plano de Ação e Referência (HTTP %d).')));
    }

    /**
     * Envia o edital selado; 5xx e falha de conexão são a única faixa retentável.
     */
    public function sendOpportunity(string $token, int $opportunityId, array $payload): SendResult
    {
        $response = $this->transport->put($this->url("/api/v1/oportunidades/{$opportunityId}"), $payload, ['token' => $token]);

        if (!$response->reachedServer() || $response->status >= 500) {
            return SendResult::unreachable($response->status, $response->transportError);
        }

        if ($response->status === 200) {
            return SendResult::ok($response->decoded() ?? []);
        }

        return SendResult::rejected($this->readDetail($response->json(), $response->status, i::__('A Plataforma CultBR recusou o envio do edital (HTTP %d).')));
    }

    private function readValidation(array $body): TokenValidation
    {
        if (($body['tipo'] ?? null) !== self::TYPE_SYSTEM) {
            return TokenValidation::reject(i::__('Este token não é um token de sistema (ente).'));
        }

        if (empty($body['valido']) || empty($body['cnpj'])) {
            return TokenValidation::reject(i::__('A Plataforma CultBR não reconheceu este token.'));
        }

        $name = $body['nome_ente'] ?? null;

        return TokenValidation::accept((string) $body['cnpj'], $name ? (string) $name : null);
    }

    /**
     * `detail` é texto em 400, 401, 403 e 404, e lista de erros de campo em 422.
     */
    private function enteDocument(array $body, string $document): string
    {
        return $this->acceptsAnyEnte ? (string) ($body['data'][0]['cnpj'] ?? $document) : $document;
    }

    private function readDetail(array $body, int $status, string $refusal): string
    {
        $detail = $body['detail'] ?? null;

        if (is_string($detail) && $detail !== '') {
            return $detail;
        }

        if (is_array($detail)) {
            $messages = array_filter(array_map(fn($error) => $error['msg'] ?? null, $detail));

            if ($messages) {
                return implode('; ', $messages);
            }
        }

        return sprintf($refusal, $status);
    }

    /**
     * O contrato não declara `servers`: os caminhos dele são absolutos a partir da origem,
     * e `/health` fica fora de `/api/v1`. Por isso o host configurado é só a origem.
     */
    private function url(string $path): string
    {
        return rtrim($this->host, '/') . $path;
    }
}
