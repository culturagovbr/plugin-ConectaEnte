<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use ConectaEnte\Services\ParInformationService;
use MapasCulturais\Entities\Opportunity;
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

    function testAnUnavailableParTreeIsLoggedWithTheEntityAndTheOpportunity()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $federativeEntity = $this->resolveFederativeEntity($opportunity);
        $this->app->mscache->delete(ParInformationService::cacheKey($federativeEntity));
        Plugin::instance()->transport = FakeTransport::unreachable();
        $log = $this->captureLog();

        $this->send($this->parInformation($opportunity->id));

        $this->assertTrue($log->hasWarningThatContains("ente {$federativeEntity->id}"));
        $this->assertTrue($log->hasWarningThatContains("oportunidade {$opportunity->id}"));
        $this->assertTrue($log->hasWarningThatContains('sem resposta da API'), 'O log diz o estado real: a tela colapsa cache vazio e recusa na mesma palavra.');
    }

    // o job de aquecimento deixa este caso passar em silêncio, e a tela abre a cada visita à aba
    function testAnEntityWithoutAParInTheCultBrIsNotLoggedAsAFailure()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $this->primeParInformationNotFound($this->resolveFederativeEntity($opportunity));
        $log = $this->captureLog();

        $this->send($this->parInformation($opportunity->id));

        $this->assertFalse($log->hasWarningThatContains('árvore do PAR'), 'Ente sem PAR é resposta legítima: registrar cada visita à aba encheria o log de ruído.');
    }

    private function registerEntity(string $token): ServerRequestInterface
    {
        return $this->requestFactory->POST('conectaente', 'federativeEntities', [], [
            'name' => 'Municipio de Arapiraca',
            'sealId' => $this->createSeal()->id,
            'token' => $token,
        ]);
    }

    private function parInformation(int $opportunityId): ServerRequestInterface
    {
        return $this->requestFactory->GET('conectaente', 'parInformation', [$opportunityId]);
    }
}
