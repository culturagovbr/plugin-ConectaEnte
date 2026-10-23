<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Client;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;

class ClientSendOpportunityTest extends TestCase
{
    const HOST = 'https://ente.conecta.hmg.cultbr.cultura.gov.br';

    function testAcceptedSendReturnsTheDecodedResponse()
    {
        $transport = FakeTransport::replying(200, ['id_par_edital' => 42, 'status' => 'aberto']);

        $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', ['id' => 7]);

        $this->assertTrue($result->accepted);
        $this->assertSame(42, $result->response['id_par_edital']);
    }

    function testSendsTheBodyAsJsonWithTheTokenHeader()
    {
        $transport = FakeTransport::replying(200, ['id_par_edital' => 1]);

        (new Client(self::HOST, $transport))->sendOpportunity('um-token', ['id' => 7, 'numero_e_titulo_edital' => 'Edital 1']);

        $this->assertSame(['id' => 7, 'numero_e_titulo_edital' => 'Edital 1'], $transport->sentBodies[0]);
        $this->assertSame(['token' => 'um-token'], $transport->sentHeaders[0]);
    }

    // o contrato da o upsert ao POST na colecao, e so atualizacao ao PUT no recurso:
    // um verbo cobre criar e atualizar, e o id do edital viaja no corpo
    function testTheSendGoesAsAPostToTheCollection()
    {
        $transport = FakeTransport::replying(200, ['id_par_edital' => 1]);

        (new Client(self::HOST, $transport))->sendOpportunity('um-token', ['id' => 7]);

        $this->assertSame('POST', $transport->sentVerbs[0], 'O PUT só atualiza: com ele, edital novo nunca é criado.');
        $this->assertSame(self::HOST . '/api/v1/oportunidades/', $transport->requestedUrls[0], 'A rota é a da coleção, sem o id no caminho.');
        $this->assertSame(7, $transport->sentBodies[0]['id'], 'E o id do edital vai no corpo, que é de onde a API o lê.');
    }

    /**
     * 400/401/403/422 encerram sem retentar; quem decide retentar é o job, a partir de `unreachable`.
     */
    function testClientErrorsAreRejectedNotUnreachable()
    {
        foreach ([400, 401, 403, 422] as $status) {
            $transport = FakeTransport::replying($status, ['detail' => 'motivo']);

            $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', []);

            $this->assertFalse($result->accepted);
            $this->assertFalse($result->unreachable, "status {$status} não pode virar unreachable");
            $this->assertSame('motivo', $result->message);
        }
    }

    /**
     * Em 422 o `detail` é lista de {loc, msg, type}: cada item precisa nomear o campo.
     */
    function testReadsFieldErrorsFromValidationResponse()
    {
        $transport = FakeTransport::replying(422, ['detail' => [
            ['loc' => ['body', 'ente_federado'], 'msg' => 'Field required', 'type' => 'missing'],
        ]]);

        $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', []);

        $this->assertSame('ente_federado: Field required', $result->message);
    }

    function testEveryRejectedFieldIsNamedInOneLine()
    {
        $transport = FakeTransport::replying(422, ['detail' => [
            ['loc' => ['body', 'ente_federado'], 'msg' => 'Field required', 'type' => 'missing'],
            ['loc' => ['body', 'forma_de_execucao'], 'msg' => 'Input should be a valid string', 'type' => 'string_type'],
        ]]);

        $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', []);

        $this->assertSame(
            'ente_federado: Field required; forma_de_execucao: Input should be a valid string',
            $result->message,
            'Os erros saem numa linha só, na ordem da resposta, cada um prefixado pelo campo.',
        );
    }

    // `loc` aninhado nomeia o item da lista; só o último segmento perderia de qual fonte se trata
    function testNestedFieldKeepsThePathWithoutTheOrigin()
    {
        $transport = FakeTransport::replying(422, ['detail' => [
            ['loc' => ['body', 'fontes_de_recurso', 0, 'valor'], 'msg' => 'Input should be a valid number', 'type' => 'float_parsing'],
        ]]);

        $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', []);

        $this->assertSame('fontes_de_recurso.0.valor: Input should be a valid number', $result->message);
    }

    function testFieldErrorWithoutLocationFallsBackToTheMessageAlone()
    {
        $transport = FakeTransport::replying(422, ['detail' => [['msg' => 'Erro sem campo', 'type' => 'value_error']]]);

        $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', []);

        $this->assertSame('Erro sem campo', $result->message, 'Sem `loc` não se inventa nome de campo: sobra a mensagem.');
    }

    // o CultBR pode devolver `detail` fora do formato declarado, e isso não pode virar erro de tipo
    function testMalformedDetailItemsFallBackInsteadOfBreaking()
    {
        $esperado = 'A Plataforma CultBR recusou o envio do edital (HTTP 422).';

        foreach ([['texto solto'], [null], [42], [[]]] as $detail) {
            $transport = FakeTransport::replying(422, ['detail' => $detail]);

            $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', []);

            $this->assertFalse($result->accepted, 'Corpo malformado não vira aceite.');
            $this->assertSame($esperado, $result->message, 'Item ilegível cai no motivo do fluxo, com o status preservado.');
        }
    }

    // erro de cabeçalho não é campo do edital: nomeá-lo mandaria o gestor procurar "token" no formulário
    function testHeaderErrorsAreNotNamedAsEditalFields()
    {
        $transport = FakeTransport::replying(422, ['detail' => [
            ['loc' => ['header', 'token'], 'msg' => 'Field required', 'type' => 'missing'],
        ]]);

        $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', []);

        $this->assertSame('Field required', $result->message);
    }

    // o fallback é por fluxo: o do envio não pode dizer que o CultBR "recusou a verificação"
    function testClientErrorWithoutUsableBodyKeepsTheStatusAndSaysItWasTheSend()
    {
        $transport = FakeTransport::replying(400, 'Bad Request');

        $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', []);

        $this->assertFalse($result->unreachable, '4xx não é indisponibilidade: não se retenta.');
        $this->assertStringContainsString('400', $result->message, 'Sem o status, o motivo não diz o que aconteceu.');
        $this->assertStringContainsString('envio', $result->message, 'O motivo gravado no edital precisa falar do envio, não da verificação de token.');
        $this->assertStringNotContainsString('verificação', $result->message);
    }

    function testServerErrorIsUnreachableAndRetryable()
    {
        $transport = FakeTransport::replying(500, 'Internal Server Error');

        $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', []);

        $this->assertTrue($result->unreachable);
        $this->assertFalse($result->accepted);
    }

    function testTransportFailureIsUnreachable()
    {
        $result = (new Client(self::HOST, FakeTransport::unreachable()))->sendOpportunity('um-token', []);

        $this->assertTrue($result->unreachable);
    }
}
