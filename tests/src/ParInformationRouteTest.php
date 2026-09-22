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

    function testOpportunityWithoutSealRespondsWithEmptyTree()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $app = $this->app;
        $app->reset();
        $app->run($this->requestFactory->GET('conectaente', 'parInformation', [$opportunity->id]), false);

        $body = json_decode((string) $app->response->getBody(), true);
        $this->assertSame([], $body['exercicios']);
    }

    function testOpportunityWithSealRespondsWithTheEntitysTree()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        Plugin::instance()->transport = FakeTransport::replying(200, ['exercicios' => [
            ['id' => '1', 'nome' => '2024', 'metas' => []],
        ]]);

        $app = $this->app;
        $app->reset();
        $app->run($this->requestFactory->GET('conectaente', 'parInformation', [$opportunity->id]), false);

        $body = json_decode((string) $app->response->getBody(), true);
        $this->assertSame('1', $body['exercicios'][0]['id']);
    }

    function testUnreachableApiRespondsWith503()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        Plugin::instance()->transport = FakeTransport::unreachable();

        $this->assertHttpStatusCode($this->requestFactory->GET('conectaente', 'parInformation', [$opportunity->id]), 503);
    }

    function testApiWithoutParInformationRespondsAsEmptyNotAsError()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        Plugin::instance()->transport = FakeTransport::replying(404, ['detail' => 'Not Found']);

        $this->assertStatus200($this->requestFactory->GET('conectaente', 'parInformation', [$opportunity->id]));
    }

    function testSavingAnInconsistentParChainIsRejected()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        Plugin::instance()->transport = FakeTransport::replying(200, ['exercicios' => [
            ['id' => '1', 'metas' => [['id' => '10', 'acoes' => [['id' => '100', 'atividades' => [['id' => '1000']]]]]]],
        ]]);

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
        $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        Plugin::instance()->transport = FakeTransport::replying(200, ['exercicios' => [
            ['id' => '1', 'metas' => [['id' => '10', 'acoes' => [['id' => '100', 'atividades' => [['id' => '1000']]]]]]],
        ]]);

        $opportunity->parExercicioId = '1';
        $opportunity->parMetaId = '10';
        // parAcaoId e parAtividadeId ficam vazios: seleção parcial não é bloqueada

        $this->assertArrayNotHasKey('parAtividadeId', $opportunity->getValidationErrors());
    }
}
