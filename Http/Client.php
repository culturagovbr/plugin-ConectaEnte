<?php

namespace ConectaEnte\Http;

use ConectaEnte\Dto\ParInformation;
use ConectaEnte\Http\Transport\CurlTransport;
use ConectaEnte\Http\Transport\JsonBody;
use ConectaEnte\Http\Transport\TransportInterface;
use MapasCulturais\i;

class Client
{
    const TYPE_SYSTEM = 'SISTEMA';
    const RESULT_FORM_MODULE = 'RESULTADO_EDITAL';

    private TransportInterface $transport;

    /** @var HttpExchange[] */
    private array $exchanges = [];

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

        // o contrato não declara 404 aqui: só o corpo do CultBR distingue "sem PAR" de um 404 de proxy
        if ($response->status === 404 && $this->isCultBrNotFound($response)) {
            return ParInformationResult::notFound();
        }

        if ($response->status === 404) {
            return ParInformationResult::unreachable();
        }

        if ($response->status >= 500) {
            return ParInformationResult::unreachable();
        }

        return ParInformationResult::rejected($this->readDetail($response->json(), $response->status, i::__('A Plataforma CultBR recusou a consulta ao Plano de Ação e Referência (HTTP %d).')));
    }

    /**
     * Envia o edital selado; 5xx e falha de conexão são a única faixa retentável.
     */
    public function sendOpportunity(string $token, array $payload): SendResult
    {
        $url = $this->sendOpportunityUrl();

        // o contrato dá o upsert ao POST, e só atualização ao PUT: um verbo cobre criar e atualizar
        $response = $this->transport->post($url, $payload, ['token' => $token]);

        $this->exchanges[] = $this->exchangeOf('POST', $url, $payload, $response);

        if (!$response->reachedServer() || $response->status >= 500) {
            return SendResult::unreachable($response->status, $response->transportError);
        }

        if ($response->status === 200) {
            return $this->readAcceptance($response);
        }

        return SendResult::rejected($this->readDetail($response->json(), $response->status, i::__('A Plataforma CultBR recusou o envio do edital (HTTP %d).')));
    }

    // o resultado do edital vai para o id_par_edital: aceitar sem ele deixaria a fase seguinte sem destino
    private function readAcceptance(Response $response): SendResult
    {
        if ($response->decoded() === null) {
            return SendResult::failed(i::__('A Plataforma CultBR respondeu ao envio do edital sem um JSON válido (HTTP 200).'));
        }

        if (!SendAcceptance::isAccepted(SendAcceptance::OPPORTUNITY, $response->status, $response->body)) {
            return SendResult::failed(i::__('A Plataforma CultBR respondeu ao envio do edital sem o id do edital (id_par_edital).'));
        }

        return SendResult::ok($response->decoded());
    }

    /** A rota do envio, para o histórico nomear o destino mesmo quando a requisição não chega a sair. */
    public function sendOpportunityUrl(): string
    {
        return $this->url('/api/v1/oportunidades/');
    }

    /** Se o edital tem planilha de resultado e quais são as colunas dela. */
    public function getResultSpreadsheetModel(string $token, int $parEditalId): ApiRead
    {
        return $this->read($token, "/api/v1/oportunidades/{$parEditalId}/resultado/modelo-planilha", i::__('A Plataforma CultBR recusou a consulta ao modelo de planilha (HTTP %d).'));
    }

    /** As questões do formulário de resultado do tipo de ação. */
    public function getResultFormStructure(string $token, int $actionTypeId): ApiRead
    {
        // com barra final a API responde 307
        $path = '/api/v1/par/formularios/estrutura?' . http_build_query(['modulo' => self::RESULT_FORM_MODULE, 'id_tipo_acao' => $actionTypeId]);

        return $this->read($token, $path, i::__('A Plataforma CultBR recusou a consulta ao formulário de resultado (HTTP %d).'));
    }

    /** O resultado publicado mais recente do edital; só o 404 com a frase do CultBR quer dizer que não há. */
    public function getPublishedResult(string $token, int $parEditalId, bool $queued = false): PublishedResult
    {
        $response = $this->transport->get($this->url("/api/v1/oportunidades/{$parEditalId}/resultado/publicado"), ['token' => $token], $queued);
        $body = $response->decoded();

        return match (true) {
            !$response->reachedServer() => PublishedResult::unknown(i::__('A Plataforma CultBR não respondeu à consulta do resultado publicado.')),
            $response->status === 200 && is_int($body['id_par_edital_resultado'] ?? null) => PublishedResult::published($body['id_par_edital_resultado'], $body),
            $response->status === 404 && ($body['detail'] ?? null) === PublishedResult::NOT_FOUND_DETAIL => PublishedResult::none(),
            default => PublishedResult::unknown(sprintf(i::__('A consulta do resultado publicado não teve resposta conclusiva (HTTP %d).'), $response->status)),
        };
    }

    /**
     * Publica o resultado do edital; o histórico pode guardar um corpo diferente do enviado.
     */
    public function sendResult(string $token, int $parEditalId, array $payload, ?array $recordedPayload = null, array $files = []): ResultSendResult
    {
        $url = $this->sendResultUrl($parEditalId);
        $response = $files
            ? $this->transport->postMultipart($url, $this->multipartFields($payload, $files), $this->multipartFiles($files), ['token' => $token])
            : $this->transport->post($url, $payload, ['token' => $token]);

        $this->exchanges[] = $this->exchangeOf('POST', $url, $recordedPayload ?? $payload, $response);

        if (!$response->reachedServer() || $response->status >= 500) {
            return ResultSendResult::unreachable($response->status, $response->transportError);
        }

        if (SendAcceptance::isAccepted(SendAcceptance::RESULT, $response->status, $response->body)) {
            return ResultSendResult::accepted($response->decoded());
        }

        $errors = ApiErrors::fromBody($response->json());

        return ResultSendResult::rejected($response->status, $errors, $errors->summary(sprintf(i::__('A Plataforma CultBR recusou o resultado do edital (HTTP %d).'), $response->status)));
    }

    // no multipart as respostas vão como texto JSON em `respostas_json`, e cada arquivo tem a descrição na mesma ordem
    private function multipartFields(array $payload, array $files): array
    {
        $fields = [];

        foreach (['id_tipo', 'id_status', 'data_publicacao'] as $key) {
            if (isset($payload[$key])) {
                $fields[] = [$key, (string) $payload[$key]];
            }
        }

        $fields[] = ['respostas_json', JsonBody::encode($payload['respostas'] ?? [])];

        foreach ($files as $file) {
            $fields[] = ['descricoes', (string) ($file['description'] ?? $file['name'])];
        }

        return $fields;
    }

    private function multipartFiles(array $files): array
    {
        return array_map(fn($file) => ['name' => 'arquivos', 'path' => $file['path'], 'filename' => $file['name'], 'mime' => $file['mime'] ?: 'application/octet-stream'], $files);
    }

    /** A rota do envio do resultado, para o histórico nomear o destino. */
    public function sendResultUrl(int $parEditalId): string
    {
        return $this->url("/api/v1/oportunidades/{$parEditalId}/resultado");
    }

    /**
     * As trocas desta execução, na ordem em que aconteceram.
     *
     * @return HttpExchange[]
     */
    public function exchanges(): array
    {
        return $this->exchanges;
    }

    // acumular, nunca sobrescrever: duas requisições numa execução são duas linhas do histórico
    private function exchangeOf(string $method, string $url, array $payload, Response $response): HttpExchange
    {
        return new HttpExchange(
            endpoint: $url,
            method: $method,
            status: $response->reachedServer() ? $response->status : null,
            requestPayload: $payload,
            responseBody: $response->body === '' ? null : $response->body,
            responseHeaders: $response->headers ?: null,
            requestId: $response->headers['x-request-id'] ?? null,
            errorMessage: $response->transportError,
            durationMs: $response->durationMs,
        );
    }

    private function read(string $token, string $path, string $refusal): ApiRead
    {
        $response = $this->transport->get($this->url($path), ['token' => $token]);
        $body = $response->decoded();

        return match (true) {
            !$response->reachedServer(), $response->status >= 500 => ApiRead::unreachable($response->transportError),
            $response->status === 200 && $body !== null => ApiRead::ok($body),
            $response->status === 200 => ApiRead::unreachable(i::__('A resposta da API não é um JSON válido.')),
            $response->status === 404 && $this->isCultBrNotFound($response) => ApiRead::notFound($body['detail']),
            $response->status === 404 => ApiRead::unreachable(),
            default => ApiRead::rejected($this->readDetail($response->json(), $response->status, $refusal)),
        };
    }

    private function isCultBrNotFound(Response $response): bool
    {
        $body = $response->decoded();

        return $body !== null && !array_is_list($body) && is_string($body['detail'] ?? null);
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
        return ApiErrors::fromBody($body)->summary(sprintf($refusal, $status));
    }

    private function url(string $path): string
    {
        return rtrim($this->host, '/') . $path;
    }
}
