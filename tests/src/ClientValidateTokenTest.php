<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Client;
use PHPUnit\Framework\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;

class ClientValidateTokenTest extends TestCase
{
    const HOST = 'https://ente.conecta.hmg.cultbr.cultura.gov.br';

    public function testAcceptsSystemTokenAndReadsTheEntity()
    {
        $transport = FakeTransport::replying(200, [
            'valido' => true,
            'tipo' => 'SISTEMA',
            'cnpj' => '12200176000176',
            'nome_ente' => 'Governo de Santa Catarina',
            'id_token' => 5,
        ]);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertTrue($validation->isAccepted());
        $this->assertSame('12200176000176', $validation->getDocument());
        $this->assertSame('Governo de Santa Catarina', $validation->getEntityName());
    }

    // a resposta real de homologação usa `nome_entidade`, não `nome_ente` como o contrato declara
    public function testAcceptsNameFromTheRealApiKey()
    {
        $transport = FakeTransport::replying(200, [
            'valido' => true,
            'tipo' => 'SISTEMA',
            'cnpj' => '12200176000176',
            'nome_entidade' => 'Governo de Santa Catarina',
        ]);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertSame('Governo de Santa Catarina', $validation->getEntityName());
    }

    public function testAcceptsTokenWithoutEntityName()
    {
        $transport = FakeTransport::replying(200, ['valido' => true, 'tipo' => 'SISTEMA', 'cnpj' => '12200176000176']);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertTrue($validation->isAccepted());
        $this->assertNull($validation->getEntityName());
    }

    public function testRejectsSuccessResponseThatDeniesTheToken()
    {
        $transport = FakeTransport::replying(200, ['valido' => false, 'tipo' => 'SISTEMA', 'cnpj' => '12200176000176']);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertFalse($validation->isAccepted());
        $this->assertFalse($validation->isUnavailable());
    }

    public function testRejectsSuccessResponseWithoutDocument()
    {
        $transport = FakeTransport::replying(200, ['valido' => true, 'tipo' => 'SISTEMA']);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertFalse($validation->isAccepted());
        $this->assertNull($validation->getDocument());
    }

    public function testSendsTheTokenInItsOwnHeader()
    {
        $transport = FakeTransport::replying(200, ['valido' => true, 'tipo' => 'SISTEMA', 'cnpj' => '12200176000176']);

        (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertSame(['token' => 'um-token'], $transport->sentHeaders[0]);
        $this->assertSame(self::HOST . '/api/v1/integracao/validar-token', $transport->requestedUrls[0]);
    }

    public function testRepeatsEachRejectionReason()
    {
        foreach (['Token de autorização necessário', 'Token inválido', 'Token expirado', 'Token revogado'] as $detail) {
            $transport = FakeTransport::replying(401, ['detail' => $detail]);

            $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

            $this->assertFalse($validation->isAccepted());
            $this->assertFalse($validation->isUnavailable());
            $this->assertSame($detail, $validation->getMessage());
        }
    }

    public function testRejectsTokenOfTheWrongTypeWithItsOwnMessage()
    {
        $transport = FakeTransport::replying(403, ['detail' => 'Token válido, mas não é um token de sistema (ente).']);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertFalse($validation->isAccepted());
        $this->assertSame('Token válido, mas não é um token de sistema (ente).', $validation->getMessage());
    }

    public function testRejectsSystemTokenWhoseTypeIsNotSystem()
    {
        $transport = FakeTransport::replying(200, ['valido' => true, 'tipo' => 'PAT', 'cnpj' => '12200176000176']);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertFalse($validation->isAccepted());
        $this->assertStringContainsString('sistema', $validation->getMessage());
    }

    public function testReadsFieldErrorsFromValidationResponse()
    {
        $transport = FakeTransport::replying(422, ['detail' => [
            ['loc' => ['header', 'token'], 'msg' => 'Field required', 'type' => 'missing'],
            ['loc' => ['header', 'token'], 'msg' => 'Invalid format', 'type' => 'value_error'],
        ]]);

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertSame('Field required; Invalid format', $validation->getMessage());
    }

    public function testTreatsServerErrorAsUnavailableAndNotAsRejection()
    {
        $transport = FakeTransport::replying(500, 'Internal Server Error');

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertTrue($validation->isUnavailable());
        $this->assertStringNotContainsString('token', mb_strtolower($validation->getMessage()));
    }

    public function testTreatsTransportFailureAsUnavailable()
    {
        $validation = (new Client(self::HOST, FakeTransport::unreachable()))->validateToken('um-token');

        $this->assertTrue($validation->isUnavailable());
        $this->assertFalse($validation->isAccepted());
    }

    public function testNeverFallsBackToAGenericError()
    {
        $transport = FakeTransport::replying(400, 'nao e json');

        $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

        $this->assertStringContainsString('400', $validation->getMessage());
        $this->assertStringContainsString('token', $validation->getMessage());
    }

    public function testHealthChecksTheRouteOutsideTheApiPrefix()
    {
        $transport = FakeTransport::replying(200, ['status' => 'ok']);

        $this->assertTrue((new Client(self::HOST, $transport))->health());
        $this->assertSame(self::HOST . '/health', $transport->requestedUrls[0]);
    }

    public function testHealthIsFalseWhenServerIsDown()
    {
        $this->assertFalse((new Client(self::HOST, FakeTransport::unreachable()))->health());
    }
}
