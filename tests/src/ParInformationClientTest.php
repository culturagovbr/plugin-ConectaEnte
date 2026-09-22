<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Client;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;

class ParInformationClientTest extends TestCase
{
    const HOST = 'https://ente.conecta.hmg.cultbr.cultura.gov.br';

    function testReadsTheFullTree()
    {
        $transport = FakeTransport::replying(200, ['exercicios' => [
            ['id' => '1', 'nome' => '2024', 'ano' => '2024', 'valor' => null, 'metas' => [
                ['id' => '10', 'nome' => 'Cultura Viva', 'ano' => null, 'valor' => null, 'acoes' => [
                    ['id' => '100', 'nome' => 'Ação A', 'ano' => null, 'valor' => '1000.00', 'atividades' => [
                        ['id' => '1000', 'nome' => 'Atividade A', 'ano' => null, 'valor' => null],
                    ]],
                ]],
            ]],
        ]]);

        $result = (new Client(self::HOST, $transport))->getParInformation('um-token');

        $this->assertNotNull($result->tree);
        $this->assertCount(1, $result->tree->exercicios);
        $this->assertSame('1', $result->tree->exercicios[0]->id);
        $this->assertSame('Cultura Viva', $result->tree->exercicios[0]->metas[0]->nome);
        $this->assertSame('1000', $result->tree->exercicios[0]->metas[0]->acoes[0]->atividades[0]->id);
    }

    /**
     * Só `id` é garantido pelo contrato: os outros campos viram null em vez de estourar.
     */
    function testToleratesMissingOptionalFieldsAtEveryLevel()
    {
        $transport = FakeTransport::replying(200, ['exercicios' => [
            ['id' => '1', 'metas' => [
                ['id' => '10', 'acoes' => [
                    ['id' => '100', 'atividades' => [
                        ['id' => '1000'],
                    ]],
                ]],
            ]],
        ]]);

        $result = (new Client(self::HOST, $transport))->getParInformation('um-token');

        $atividade = $result->tree->exercicios[0]->metas[0]->acoes[0]->atividades[0];
        $this->assertSame('1000', $atividade->id);
        $this->assertNull($atividade->nome);
    }

    function testTreatsNotFoundAsMissingEndpointNotAsError()
    {
        $transport = FakeTransport::replying(404, ['detail' => 'Not Found']);

        $result = (new Client(self::HOST, $transport))->getParInformation('um-token');

        $this->assertTrue($result->notFound);
        $this->assertFalse($result->unreachable);
        $this->assertNull($result->tree);
    }

    function testRejectsBadToken()
    {
        $transport = FakeTransport::replying(401, ['detail' => 'Token inválido']);

        $result = (new Client(self::HOST, $transport))->getParInformation('um-token');

        $this->assertFalse($result->unreachable);
        $this->assertFalse($result->notFound);
        $this->assertNull($result->tree);
        $this->assertSame('Token inválido', $result->message);
    }

    function testTreatsServerErrorAsUnavailable()
    {
        $transport = FakeTransport::replying(500, 'Internal Server Error');

        $result = (new Client(self::HOST, $transport))->getParInformation('um-token');

        $this->assertTrue($result->unreachable);
    }

    function testTreatsTransportFailureAsUnavailable()
    {
        $result = (new Client(self::HOST, FakeTransport::unreachable()))->getParInformation('um-token');

        $this->assertTrue($result->unreachable);
    }

    function testSendsTheTokenInItsOwnHeaderAgainstTheParInformationPath()
    {
        $transport = FakeTransport::replying(200, ['exercicios' => []]);

        (new Client(self::HOST, $transport))->getParInformation('um-token');

        $this->assertSame(['token' => 'um-token'], $transport->sentHeaders[0]);
        $this->assertSame(self::HOST . '/api/v1/par-information', $transport->requestedUrls[0]);
    }
}
