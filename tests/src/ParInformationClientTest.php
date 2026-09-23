<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Client;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;

class ParInformationClientTest extends TestCase
{
    const HOST = 'https://ente.conecta.hmg.cultbr.cultura.gov.br';
    const DOCUMENT = '12345678000190';

    private function treeFor(string $cnpj, array $exercicios): array
    {
        return ['pagination' => ['skip' => 0, 'limit' => 20, 'total' => 1], 'data' => [
            ['nome' => 'Governo de Santa Catarina', 'cnpj' => $cnpj, 'exercicios' => $exercicios],
        ]];
    }

    function testReadsTheFullTree()
    {
        $transport = FakeTransport::replying(200, $this->treeFor(self::DOCUMENT, [
            ['id' => '1', 'nome' => '2024', 'ano' => '2024', 'valor' => null, 'metas' => [
                ['id' => '10', 'nome' => 'Cultura Viva', 'ano' => null, 'valor' => null, 'acoes' => [
                    ['id' => '100', 'nome' => 'Ação A', 'ano' => null, 'valor' => '1000.00', 'atividades' => [
                        ['id' => '1000', 'nome' => 'Atividade A', 'ano' => null, 'valor' => null],
                    ]],
                ]],
            ]],
        ]));

        $result = (new Client(self::HOST, $transport))->getParInformation('um-token', self::DOCUMENT);

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
        $transport = FakeTransport::replying(200, $this->treeFor(self::DOCUMENT, [
            ['id' => '1', 'metas' => [
                ['id' => '10', 'acoes' => [
                    ['id' => '100', 'atividades' => [
                        ['id' => '1000'],
                    ]],
                ]],
            ]],
        ]));

        $result = (new Client(self::HOST, $transport))->getParInformation('um-token', self::DOCUMENT);

        $atividade = $result->tree->exercicios[0]->metas[0]->acoes[0]->atividades[0];
        $this->assertSame('1000', $atividade->id);
        $this->assertNull($atividade->nome);
    }

    /**
     * `data` é uma lista de entes: casa pelo cnpj, não assume `data[0]`.
     */
    function testMatchesTheEntityByCnpjAmongSeveral()
    {
        $body = [
            'pagination' => ['skip' => 0, 'limit' => 20, 'total' => 2],
            'data' => [
                ['nome' => 'Outro Ente', 'cnpj' => '00000000000000', 'exercicios' => [['id' => 'outro']]],
                ['nome' => 'Governo de Santa Catarina', 'cnpj' => self::DOCUMENT, 'exercicios' => [['id' => '1']]],
            ],
        ];

        $result = (new Client(self::HOST, FakeTransport::replying(200, $body)))->getParInformation('um-token', self::DOCUMENT);

        $this->assertCount(1, $result->tree->exercicios);
        $this->assertSame('1', $result->tree->exercicios[0]->id);
    }

    /**
     * O cnpj pode vir pontuado na resposta ou no cadastro: a comparação ignora pontuação.
     */
    function testMatchesTheCnpjIgnoringPunctuation()
    {
        $body = $this->treeFor('12.345.678/0001-90', [['id' => '1']]);

        $result = (new Client(self::HOST, FakeTransport::replying(200, $body)))->getParInformation('um-token', self::DOCUMENT);

        $this->assertCount(1, $result->tree->exercicios);
    }

    function testReturnsEmptyTreeWhenNoItemMatchesTheDocument()
    {
        $body = $this->treeFor('00000000000000', [['id' => '1']]);

        $result = (new Client(self::HOST, FakeTransport::replying(200, $body)))->getParInformation('um-token', self::DOCUMENT);

        $this->assertNotNull($result->tree);
        $this->assertSame([], $result->tree->exercicios);
    }

    function testTreatsNotFoundAsMissingEndpointNotAsError()
    {
        $transport = FakeTransport::replying(404, ['detail' => 'Not Found']);

        $result = (new Client(self::HOST, $transport))->getParInformation('um-token', self::DOCUMENT);

        $this->assertTrue($result->notFound);
        $this->assertFalse($result->unreachable);
        $this->assertNull($result->tree);
    }

    function testRejectsBadToken()
    {
        $transport = FakeTransport::replying(401, ['detail' => 'Token inválido']);

        $result = (new Client(self::HOST, $transport))->getParInformation('um-token', self::DOCUMENT);

        $this->assertFalse($result->unreachable);
        $this->assertFalse($result->notFound);
        $this->assertNull($result->tree);
        $this->assertSame('Token inválido', $result->message);
    }

    function testTreatsServerErrorAsUnavailable()
    {
        $transport = FakeTransport::replying(500, 'Internal Server Error');

        $result = (new Client(self::HOST, $transport))->getParInformation('um-token', self::DOCUMENT);

        $this->assertTrue($result->unreachable);
    }

    function testTreatsTransportFailureAsUnavailable()
    {
        $result = (new Client(self::HOST, FakeTransport::unreachable()))->getParInformation('um-token', self::DOCUMENT);

        $this->assertTrue($result->unreachable);
    }

    function testSendsTheTokenInItsOwnHeaderAgainstTheParInformationPath()
    {
        $transport = FakeTransport::replying(200, ['pagination' => [], 'data' => []]);

        (new Client(self::HOST, $transport))->getParInformation('um-token', self::DOCUMENT);

        $this->assertSame(['token' => 'um-token'], $transport->sentHeaders[0]);
        $this->assertSame(self::HOST . '/api/v1/par-information', $transport->requestedUrls[0]);
    }
}
