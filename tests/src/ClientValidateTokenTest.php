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

    // `valido` falso nunca pode ser reportado como "token válido, mas de outro tipo"
    public function testInvalidTokenIsNotReportedAsWrongTypeWhateverTheType()
    {
        foreach ([[], ['tipo' => 'PAT'], ['tipo' => 'SISTEMA'], ['tipo' => null]] as $extra) {
            $body = array_merge(['valido' => false, 'cnpj' => '12200176000176'], $extra);

            $validation = (new Client(self::HOST, FakeTransport::replying(200, $body)))->validateToken('um-token');

            $this->assertFalse($validation->isAccepted());
            $this->assertNotSame(Client::WRONG_TYPE_MESSAGE, $validation->getMessage());
        }
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
        foreach ([['detail' => 'Token válido, mas não é um token de sistema (ente).'], 'Forbidden', []] as $body) {
            $validation = (new Client(self::HOST, FakeTransport::replying(403, $body)))->validateToken('um-token');

            $this->assertFalse($validation->isAccepted());
            $this->assertFalse($validation->isUnavailable());
            $this->assertSame(Client::WRONG_TYPE_MESSAGE, $validation->getMessage());
        }
    }

    public function testWrongTypeMessageDiffersFromEveryInvalidTokenMessage()
    {
        foreach (['Token de autorização necessário', 'Token inválido', 'Token expirado', 'Token revogado'] as $detail) {
            $validation = (new Client(self::HOST, FakeTransport::replying(401, ['detail' => $detail])))->validateToken('um-token');

            $this->assertNotSame(Client::WRONG_TYPE_MESSAGE, $validation->getMessage());
        }
    }

    public function testEmptyTokenIsRejectedWithoutCallingTheApi()
    {
        foreach (['', '   ', null] as $token) {
            $transport = FakeTransport::replying(200, ['valido' => true, 'tipo' => 'SISTEMA', 'cnpj' => '12200176000176']);

            $validation = (new Client(self::HOST, $transport))->validateToken($token);

            $this->assertFalse($validation->isAccepted());
            $this->assertFalse($validation->isUnavailable());
            $this->assertSame([], $transport->requestedUrls);
        }
    }

    public function testRejectsDocumentThatIsNotFourteenDigits()
    {
        foreach (['1220017600017', '122001760001766', '12.200.176/0001-76', 'abcdefghijklmn', "12200176000176\n", " 12200176000176"] as $cnpj) {
            $transport = FakeTransport::replying(200, ['valido' => true, 'tipo' => 'SISTEMA', 'cnpj' => $cnpj]);

            $validation = (new Client(self::HOST, $transport))->validateToken('um-token');

            $this->assertFalse($validation->isAccepted());
            $this->assertNull($validation->getDocument());
        }
    }

    public function testNoMessageEchoesTheToken()
    {
        $token = 'cult_segredo-do-ente';
        $replies = [
            FakeTransport::replying(401, ['detail' => 'Token inválido']),
            FakeTransport::replying(403, []),
            FakeTransport::replying(400, 'nao e json'),
            FakeTransport::replying(422, ['detail' => [['loc' => ['header', 'token'], 'msg' => 'Field required']]]),
            FakeTransport::replying(500, 'Internal Server Error'),
            FakeTransport::replying(200, ['valido' => false, 'tipo' => 'SISTEMA']),
            FakeTransport::unreachable(),
        ];

        foreach ($replies as $transport) {
            $validation = (new Client(self::HOST, $transport))->validateToken($token);

            $this->assertStringNotContainsString($token, (string) $validation->getMessage());
        }
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
