<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Client;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;

/**
 * O que o cliente faz de cada desfecho de `GET /api/v1/par-information`.
 */
class ParInformationClientTest extends TestCase
{
    const HOST = 'https://ente.conecta.hmg.cultbr.cultura.gov.br';
    const DOCUMENT = '12200176000176';

    function testSendsTheTokenInItsOwnHeader()
    {
        $transport = FakeTransport::replying(200, ['data' => []]);

        (new Client(self::HOST, $transport))->getParInformation('um-token', self::DOCUMENT);

        $this->assertSame(self::HOST . '/api/v1/par-information', $transport->requestedUrls[0]);
        $this->assertSame(['token' => 'um-token'], $transport->sentHeaders[0]);
    }

    function testReadsTheTreeOfTheEntityThatOwnsTheToken()
    {
        $result = $this->get(200, ['data' => [
            ['cnpj' => '99999999000191', 'exercicios' => [['id' => 'de-outro']]],
            ['cnpj' => self::DOCUMENT, 'exercicios' => [['id' => '2024', 'ano' => '2024']]],
        ]]);

        $this->assertNotNull($result->tree);
        $this->assertSame('2024', $result->tree->exercises[0]->id);
        $this->assertFalse($result->unreachable);
        $this->assertFalse($result->notFound);
    }

    function testEntityWithoutParGivesAnEmptyTreeAndNotAFailure()
    {
        $result = $this->get(200, ['data' => [['cnpj' => self::DOCUMENT, 'exercicios' => []]]]);

        $this->assertNotNull($result->tree, 'Árvore vazia é resposta boa: o job pode guardá-la.');
        $this->assertSame([], $result->tree->exercises);
    }

    function testBodyThatIsNotJsonIsTreatedAsUnreachable()
    {
        $result = $this->get(200, '<html>502 Bad Gateway</html>');

        $this->assertNull($result->tree, 'Corpo imprestável viraria "ente sem PAR" no cache.');
        $this->assertTrue($result->unreachable);
        $this->assertNotNull($result->message);
    }

    function testMissingPathIsReportedAsNotFound()
    {
        $result = $this->get(404, ['detail' => 'Not Found']);

        $this->assertTrue($result->notFound, 'O contrato reduzido de produção pode não expor este caminho.');
        $this->assertFalse($result->unreachable);
        $this->assertNull($result->tree);
    }

    function testRejectedTokenCarriesTheReason()
    {
        $result = $this->get(401, ['detail' => 'Token inválido']);

        $this->assertNull($result->tree);
        $this->assertFalse($result->unreachable);
        $this->assertFalse($result->notFound);
        $this->assertStringContainsString('Token inválido', $result->message);
    }

    function testServerErrorIsUnreachableSoTheCacheIsKept()
    {
        $result = $this->get(500, ['detail' => 'Internal Server Error']);

        $this->assertTrue($result->unreachable);
        $this->assertNull($result->tree);
    }

    function testTransportFailureIsUnreachable()
    {
        $result = (new Client(self::HOST, FakeTransport::unreachable()))->getParInformation('um-token', self::DOCUMENT);

        $this->assertTrue($result->unreachable);
        $this->assertNull($result->tree);
    }

    function testDocumentIsMatchedIgnoringPunctuation()
    {
        $result = $this->get(200, ['data' => [
            ['cnpj' => '12.200.176/0001-76', 'exercicios' => [['id' => '2024']]],
        ]]);

        $this->assertSame('2024', $result->tree->exercises[0]->id);
    }

    function testResponseWithoutTheEntityGivesAnEmptyTree()
    {
        $result = $this->get(200, ['data' => [['cnpj' => '99999999000191', 'exercicios' => [['id' => 'de-outro']]]]]);

        $this->assertNotNull($result->tree);
        $this->assertSame([], $result->tree->exercises, 'Sem o ente do token, não há árvore a oferecer.');
    }

    private function get(int $status, array|string $body): \ConectaEnte\Http\ParInformationResult
    {
        return (new Client(self::HOST, FakeTransport::replying($status, $body)))
            ->getParInformation('um-token', self::DOCUMENT);
    }
}
