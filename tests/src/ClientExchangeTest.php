<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Client;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;

/**
 * O que o cliente guarda de cada troca com o CultBR, para o histórico gravar uma linha por requisição.
 */
class ClientExchangeTest extends TestCase
{
    function testEachRequestBecomesItsOwnExchange()
    {
        $client = $this->clientReplying(201);

        $client->sendOpportunity('token-1', ['nome' => 'Primeiro']);
        $client->sendOpportunity('token-1', ['nome' => 'Segundo']);

        $exchanges = $client->exchanges();
        $this->assertCount(2, $exchanges, 'Sobrescrever a troca anterior apaga do histórico a requisição que falhou primeiro.');
        $this->assertSame(['Primeiro', 'Segundo'], array_map(fn ($troca) => $troca->requestPayload['nome'], $exchanges));
    }

    function testTheExchangeCarriesWhatTheHistoryNeeds()
    {
        $client = $this->clientReplying(201, ['id_par_edital' => 53781], ['x-request-id' => 'req-7'], 142);

        $client->sendOpportunity('token-1', ['nome' => 'Edital']);

        $troca = $client->exchanges()[0];
        $this->assertSame('POST', $troca->method);
        $this->assertStringEndsWith('/api/v1/oportunidades/', $troca->endpoint);
        $this->assertSame(201, $troca->status);
        $this->assertSame(['nome' => 'Edital'], $troca->requestPayload);
        $this->assertSame(['x-request-id' => 'req-7'], $troca->responseHeaders);
        $this->assertSame('req-7', $troca->requestId);
        $this->assertSame(142, $troca->durationMs);
        $this->assertStringContainsString('53781', $troca->responseBody);
        $this->assertNull($troca->errorMessage);
    }

    function testTheRequestHeadersNeverReachTheExchange()
    {
        $client = $this->clientReplying(201);

        $client->sendOpportunity('token-secreto', ['nome' => 'Edital']);

        $this->assertStringNotContainsString('token-secreto', json_encode($client->exchanges()[0]), 'O token é cabeçalho de requisição, e cabeçalho de requisição não é gravado.');
    }

    function testAServerThatDidNotAnswerBecomesAnExchangeWithoutStatus()
    {
        $client = new Client('https://cultbr.example', FakeTransport::unreachable());

        $client->sendOpportunity('token-1', ['nome' => 'Edital']);

        $troca = $client->exchanges()[0];
        $this->assertNull($troca->status, 'Sem resposta não há status: gravar zero inventaria uma resposta que não houve.');
        $this->assertNull($troca->responseBody);
        $this->assertSame('Could not resolve host', $troca->errorMessage);
    }

    private function clientReplying(int $status, array $body = [], array $headers = [], ?int $durationMs = null): Client
    {
        return new Client('https://cultbr.example', FakeTransport::replying($status, $body, $headers, $durationMs));
    }
}
