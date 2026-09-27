<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;
use Tests\Traits\RequestFactory;

class ParInformationRouteTest extends TestCase
{
    use ConectaEnteFixtures;
    use RequestFactory;

    private array $exercicios = [
        ['id' => '1', 'metas' => [['id' => '10', 'acoes' => [['id' => '100', 'atividades' => [['id' => '1000']]]]]]],
    ];

    function setUp(): void
    {
        parent::setUp();
        Plugin::instance()->transport = null;
    }

    function testGuestCannotAccess()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $this->logout();

        $this->assertStatus401($this->requestFactory->GET('conectaente', 'parInformation', [$opportunity->id]));
    }

    function testUnrelatedUserCannotAccess()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $this->login($this->userDirector->createUser());

        $this->assertStatus403($this->requestFactory->GET('conectaente', 'parInformation', [$opportunity->id]));
    }

    function testOwnerCanAccess()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $this->assertStatus200($this->requestFactory->GET('conectaente', 'parInformation', [$opportunity->id]));
    }

    function testOpportunityWithoutSealRespondsAsAvailableWithEmptyTree()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $app = $this->app;
        $app->reset();
        $app->run($this->requestFactory->GET('conectaente', 'parInformation', [$opportunity->id]), false);

        $body = json_decode((string) $app->response->getBody(), true);
        $this->assertTrue($body['available']);
        $this->assertSame([], $body['exercicios']);
    }

    function testOpportunityWithSealAndPopulatedCacheRespondsWithTheEntitysTree()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        $this->primeParInformationCache($federativeEntity, $this->exercicios);

        $app = $this->app;
        $app->reset();
        $app->run($this->requestFactory->GET('conectaente', 'parInformation', [$opportunity->id]), false);

        $body = json_decode((string) $app->response->getBody(), true);
        $this->assertTrue($body['available']);
        $this->assertSame('1', $body['exercicios'][0]['id']);
    }

    /**
     * Cache nunca populado (job não rodou/não teve sucesso ainda para este ente):
     * a requisição não tenta chamar a API, só reporta "indisponível".
     */
    function testOpportunityWithSealAndEmptyCacheRespondsAsUnavailable()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        // qualquer chamada real quebraria o teste
        Plugin::instance()->transport = FakeTransport::unreachable();

        $app = $this->app;
        $app->reset();
        $app->run($this->requestFactory->GET('conectaente', 'parInformation', [$opportunity->id]), false);

        $body = json_decode((string) $app->response->getBody(), true);
        $this->assertFalse($body['available']);
        $this->assertSame([], $body['exercicios']);
    }

    function testSavingAnInconsistentParChainIsRejected()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        $this->primeParInformationCache($federativeEntity, $this->exercicios);

        $opportunity->parExercicioId = '1';
        $opportunity->parMetaId = '10';
        $opportunity->parAcaoId = '100';
        $opportunity->parAtividadeId = '9999'; // não pertence à ação 100

        $this->assertArrayHasKey('parAtividadeId', $opportunity->getValidationErrors());
    }

    function testSavingAPartialParSelectionIsAccepted()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        $this->primeParInformationCache($federativeEntity, $this->exercicios);

        $opportunity->parExercicioId = '1';
        $opportunity->parMetaId = '10';
        // parAcaoId e parAtividadeId ficam vazios: seleção parcial não é bloqueada

        $this->assertArrayNotHasKey('parAtividadeId', $opportunity->getValidationErrors());
    }
}
