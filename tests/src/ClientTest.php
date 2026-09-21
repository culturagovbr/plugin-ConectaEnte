<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Client;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;

class ClientTest extends TestCase
{
    const HOST = 'https://ente.conecta.hmg.cultbr.cultura.gov.br';

    function testAcceptsSystemTokenAndReadsTheEntity()
    {
        $transport = FakeTransport::replying(200, [
            'valido' => true,
            'tipo' => 'SISTEMA',
            'cnpj' => '12200176000176',
            'nome_ente' => 'Governo de Santa Catarina',
            'id_token' => 5,
        ]);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertTrue($validation->accepted);
        $this->assertSame('12200176000176', $validation->document);
        $this->assertSame('Governo de Santa Catarina', $validation->entityName);
    }

    function testAcceptsTokenWithoutEntityName()
    {
        $transport = FakeTransport::replying(200, ['valido' => true, 'tipo' => 'SISTEMA', 'cnpj' => '12200176000176']);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertTrue($validation->accepted);
        $this->assertNull($validation->entityName);
    }

    /**
     * Resposta incompleta não pode virar cadastro aceito, mesmo o contrato declarando
     * `valido` e `cnpj` obrigatórios no 200.
     */
    function testRejectsSuccessResponseThatDeniesTheToken()
    {
        $transport = FakeTransport::replying(200, ['valido' => false, 'tipo' => 'SISTEMA', 'cnpj' => '12200176000176']);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertFalse($validation->accepted);
        $this->assertFalse($validation->unavailable);
    }

    function testRejectsSuccessResponseWithoutDocument()
    {
        $transport = FakeTransport::replying(200, ['valido' => true, 'tipo' => 'SISTEMA']);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertFalse($validation->accepted);
        $this->assertNull($validation->document);
    }

    function testSendsTheTokenInItsOwnHeader()
    {
        $transport = FakeTransport::replying(200, ['valido' => true, 'tipo' => 'SISTEMA', 'cnpj' => '12200176000176']);

        (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertSame(['token' => 'um-token'], $transport->sentHeaders[0]);
        $this->assertSame(self::HOST . '/api/v1/integracao/validar-token', $transport->requestedUrls[0]);
    }

    /**
     * Os quatro motivos de 401 chegam em `detail` e precisam ser repetidos como vieram.
     */
    function testRepeatsEachRejectionReason()
    {
        foreach (['Token de autorização necessário', 'Token inválido', 'Token expirado', 'Token revogado'] as $detail) {
            $transport = FakeTransport::replying(401, ['detail' => $detail]);

            $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

            $this->assertFalse($validation->accepted);
            $this->assertFalse($validation->unavailable);
            $this->assertSame($detail, $validation->message);
        }
    }

    function testRejectsTokenOfTheWrongTypeWithItsOwnMessage()
    {
        $transport = FakeTransport::replying(403, ['detail' => 'Token válido, mas não é um token de sistema (ente).']);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertFalse($validation->accepted);
        $this->assertSame('Token válido, mas não é um token de sistema (ente).', $validation->message);
    }

    function testRejectsSystemTokenWhoseTypeIsNotSystem()
    {
        $transport = FakeTransport::replying(200, ['valido' => true, 'tipo' => 'PAT', 'cnpj' => '12200176000176']);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertFalse($validation->accepted);
        $this->assertStringContainsString('sistema', $validation->message);
    }

    /**
     * Em 422 o `detail` é lista de {loc, msg, type}: ler como texto perderia a informação.
     */
    function testReadsFieldErrorsFromValidationResponse()
    {
        $transport = FakeTransport::replying(422, ['detail' => [
            ['loc' => ['header', 'token'], 'msg' => 'Field required', 'type' => 'missing'],
            ['loc' => ['header', 'token'], 'msg' => 'Invalid format', 'type' => 'value_error'],
        ]]);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertSame('Field required; Invalid format', $validation->message);
    }

    function testTreatsServerErrorAsUnavailableAndNotAsRejection()
    {
        $transport = FakeTransport::replying(500, 'Internal Server Error');

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertTrue($validation->unavailable);
        $this->assertStringNotContainsString('token', mb_strtolower($validation->message));
    }

    function testTreatsTransportFailureAsUnavailable()
    {
        $validation = (new Client(self::HOST, FakeTransport::unreachable()))->validateToken('um-token');

        $this->assertTrue($validation->unavailable);
        $this->assertFalse($validation->accepted);
    }

    function testNeverFallsBackToAGenericError()
    {
        $transport = FakeTransport::replying(400, 'nao e json');

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertStringContainsString('400', $validation->message);
    }

    function testHealthChecksTheRouteOutsideTheApiPrefix()
    {
        $transport = FakeTransport::replying(200, ['status' => 'ok']);

        $this->assertTrue((new Client(self::HOST, $transport))->isHealthy());
        $this->assertSame('https://ente.conecta.hmg.cultbr.cultura.gov.br/health', $transport->requestedUrls[0]);
    }
}
