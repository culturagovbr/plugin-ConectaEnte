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

        $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', 7, ['id' => 7]);

        $this->assertTrue($result->accepted);
        $this->assertSame(42, $result->response['id_par_edital']);
    }

    function testSendsTheBodyAsJsonWithTheTokenHeader()
    {
        $transport = FakeTransport::replying(200, ['id_par_edital' => 1]);

        (new Client(self::HOST, $transport))->sendOpportunity('um-token', 7, ['id' => 7, 'numero_e_titulo_edital' => 'Edital 1']);

        $this->assertSame(['id' => 7, 'numero_e_titulo_edital' => 'Edital 1'], $transport->sentBodies[0]);
        $this->assertSame(['token' => 'um-token'], $transport->sentHeaders[0]);
        $this->assertSame(self::HOST . '/api/v1/oportunidades/7', $transport->requestedUrls[0]);
    }

    /**
     * 400/401/403/422 encerram sem retentar; quem decide retentar é o job, a partir de `unreachable`.
     */
    function testClientErrorsAreRejectedNotUnreachable()
    {
        foreach ([400, 401, 403, 422] as $status) {
            $transport = FakeTransport::replying($status, ['detail' => 'motivo']);

            $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', 7, []);

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

        $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', 7, []);

        $this->assertSame('Field required', $result->message);
    }

    function testServerErrorIsUnreachableAndRetryable()
    {
        $transport = FakeTransport::replying(500, 'Internal Server Error');

        $result = (new Client(self::HOST, $transport))->sendOpportunity('um-token', 7, []);

        $this->assertTrue($result->unreachable);
        $this->assertFalse($result->accepted);
    }

    function testTransportFailureIsUnreachable()
    {
        $result = (new Client(self::HOST, FakeTransport::unreachable()))->sendOpportunity('um-token', 7, []);

        $this->assertTrue($result->unreachable);
    }
}
