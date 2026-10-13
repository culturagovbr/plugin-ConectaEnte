<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\CapturesLog;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

/**
 * O que não entra na tabela do histórico — token e PAR — deixa linha no log de aplicação.
 */
class IntegrationLoggingTest extends TestCase
{
    use CapturesLog;
    use PublicationRequirementsFixtures;
    use RequestFactory;

    protected function tearDown(): void
    {
        $this->popCapturedLog();
        Plugin::instance()->transport = null;

        parent::tearDown();
    }

    function testARefusedTokenLeavesTheMessageOfTheCultBrInTheLog()
    {
        $this->loginAsSaasSuperAdmin();
        Plugin::instance()->transport = FakeTransport::replying(401, ['detail' => 'Token expirado']);
        $log = $this->captureLog();

        $this->send($this->registerEntity('um-token'));

        $this->assertTrue(
            $log->hasWarningThatContains('Token expirado'),
            'O motivo do CultBR chega à tela e morre nela: sem o registro, ninguém depura a recusa depois.'
        );
    }

    function testAnApiOutOfReachIsLoggedAsUnavailableInsteadOfRefused()
    {
        $this->loginAsSaasSuperAdmin();
        Plugin::instance()->transport = FakeTransport::unreachable();
        $log = $this->captureLog();

        $this->send($this->registerEntity('um-token'));

        $this->assertTrue($log->hasWarningThatContains('indisponível'), 'Token errado se corrige; API fora do ar se espera.');
        $this->assertFalse($log->hasWarningThatContains('token recusado'));
    }

    function testTheTokenItselfNeverReachesTheLog()
    {
        $this->loginAsSaasSuperAdmin();
        Plugin::instance()->transport = FakeTransport::replying(401, ['detail' => 'Token expirado']);
        $log = $this->captureLog();

        $this->send($this->registerEntity('segredo-do-ente'));

        $this->assertFalse($log->hasWarningThatContains('segredo-do-ente'), 'O token é a credencial do Ente: registrar o motivo não pode significar registrar a chave.');
    }

    function testChangingTheTokenOfAnEntityNamesItInTheLog()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        Plugin::instance()->transport = FakeTransport::replying(401, ['detail' => 'Token revogado']);
        $log = $this->captureLog();

        $request = $this->requestFactory->PATCH('conectaente', 'federativeEntity', [$federativeEntity->id], ['token' => 'outro-token']);
        $this->send($request);

        $this->assertTrue($log->hasWarningThatContains("ente {$federativeEntity->id}"), 'Sem o ente nomeado, a linha não serve para investigar nada.');
        $this->assertTrue($log->hasWarningThatContains('Token revogado'));
    }

    private function registerEntity(string $token): ServerRequestInterface
    {
        return $this->requestFactory->POST('conectaente', 'federativeEntities', [], [
            'name' => 'Municipio de Arapiraca',
            'sealId' => $this->createSeal()->id,
            'token' => $token,
        ]);
    }

}
