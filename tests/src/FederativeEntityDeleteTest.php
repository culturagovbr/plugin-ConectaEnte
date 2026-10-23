<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Plugin;
use MapasCulturais\Exceptions\BadRequest;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;
use Tests\Traits\RequestFactory;

class FederativeEntityDeleteTest extends TestCase
{
    use ConectaEnteFixtures;
    use RequestFactory;

    protected function tearDown(): void
    {
        Plugin::instance()->transport = null;

        parent::tearDown();
    }

    function testDeletingSendsToTrashAndOutOfTheList()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');
        $federativeEntity = $this->createFederativeEntityWithSeal($this->createSeal());

        $this->assertStatus200($this->requestFactory->DELETE('conectaente', 'federativeEntity', [$federativeEntity->id], ['password' => 'segredo']));

        $this->assertSame([], $this->panelProp('entities'));
        $this->assertSame($federativeEntity->id, $this->panelProp('trashed')[0]['id']);
    }

    function testTrashedFederativeEntityDoesNotResolveTheOpportunity()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        $federativeEntity->delete(true);

        $this->assertNull($this->resolveFederativeEntity($opportunity));
    }

    function testSealOfTrashedFederativeEntityStillBlocksAnotherFederativeSeal()
    {
        $this->loginAsSaasSuperAdmin();
        $trashedSeal = $this->createSeal();
        $this->createFederativeEntityWithSeal($trashedSeal)->delete(true);
        $otherSeal = $this->createSeal();
        $this->createFederativeEntityWithSeal($otherSeal, 'Outro ente', '98765432000199');
        $opportunity = $this->createOpportunityWithSeal($trashedSeal);

        $this->expectException(BadRequest::class);

        $opportunity->createSealRelation($otherSeal);
    }

    function testDocumentOfTrashedFederativeEntityStillRefusesRegistration()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntity('Na lixeira', '12200176000176')->delete(true);
        Plugin::instance()->transport = FakeTransport::replying(200, ['valido' => true, 'tipo' => 'SISTEMA', 'cnpj' => '12200176000176', 'nome_ente' => 'MUNICIPIO DE ARAPIRACA']);

        $request = $this->requestFactory->POST('conectaente', 'federativeEntities', [], [
            'name' => 'De novo',
            'sealId' => $this->createSeal()->id,
            'token' => 'um-token',
        ]);

        $this->assertStatus400($request);
        $this->assertCount(1, $this->app->repo(FederativeEntity::class)->findAll());
    }

    function testUndeletingBringsItBackToTheListAndToTheResolver()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);
        $federativeEntity->delete(true);

        $this->assertStatus200($this->requestFactory->POST('conectaente', 'federativeEntityUndelete', [$federativeEntity->id], ['password' => 'segredo']));

        $this->assertSame($federativeEntity->id, $this->panelProp('entities')[0]['id']);
        $this->assertSame([], $this->panelProp('trashed'));
        $this->assertSame($federativeEntity->id, $this->resolveFederativeEntity($opportunity)->id);
    }

    function testDestroyingRemovesTheFederativeEntityAndItsSealLink()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);
        $federativeEntity->delete(true);
        $id = $federativeEntity->id;

        $this->assertStatus200($this->requestFactory->DELETE('conectaente', 'federativeEntityDestroy', [$id], ['password' => 'segredo']));

        $this->app->em->clear();
        $this->assertNull($this->app->repo(FederativeEntity::class)->find($id));
        $this->assertNull($this->app->repo(FederativeEntitySeal::class)->findOneBySeal($seal));
    }

    function testWrongPasswordDoesNotDeleteUndeleteOrDestroy()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');
        $active = $this->createFederativeEntity('Ativo', '12345678000190');
        $trashed = $this->createFederativeEntity('Na lixeira', '98765432000199');
        $trashed->delete(true);

        $this->assertStatus403($this->requestFactory->DELETE('conectaente', 'federativeEntity', [$active->id], ['password' => 'outra']));
        $this->assertStatus403($this->requestFactory->POST('conectaente', 'federativeEntityUndelete', [$trashed->id], ['password' => 'outra']));
        $this->assertStatus403($this->requestFactory->DELETE('conectaente', 'federativeEntityDestroy', [$trashed->id], ['password' => 'outra']));

        $this->app->em->clear();
        $this->assertSame(FederativeEntity::STATUS_ENABLED, (int) $this->app->repo(FederativeEntity::class)->find($active->id)->status);
        $this->assertSame(FederativeEntity::STATUS_TRASH, (int) $this->app->repo(FederativeEntity::class)->find($trashed->id)->status);
    }

    function testAdminWithoutLocalPasswordCannotDelete()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();

        $this->assertStatus400($this->requestFactory->DELETE('conectaente', 'federativeEntity', [$federativeEntity->id], ['password' => 'qualquer']));

        $this->app->em->clear();
        $this->assertSame(FederativeEntity::STATUS_ENABLED, (int) $this->app->repo(FederativeEntity::class)->find($federativeEntity->id)->status);
    }

    function testRegularUserIsRefusedBeforeTheFederativeEntityIsLookedUp()
    {
        $this->login($this->userDirector->createUser());

        $this->assertStatus403($this->requestFactory->DELETE('conectaente', 'federativeEntity', [999999]));
        $this->assertStatus403($this->requestFactory->POST('conectaente', 'federativeEntityUndelete', [999999], []));
        $this->assertStatus403($this->requestFactory->DELETE('conectaente', 'federativeEntityDestroy', [999999]));
    }

    function testRegularUserCannotDeleteUndeleteOrDestroy()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntityWithSeal($this->createSeal());
        $this->login($this->userDirector->createUser());

        $this->assertStatus403($this->requestFactory->DELETE('conectaente', 'federativeEntity', [$federativeEntity->id]));
        $this->assertStatus403($this->requestFactory->POST('conectaente', 'federativeEntityUndelete', [$federativeEntity->id], []));
        $this->assertStatus403($this->requestFactory->DELETE('conectaente', 'federativeEntityDestroy', [$federativeEntity->id]));

        $this->app->em->clear();
        $this->assertSame(FederativeEntity::STATUS_ENABLED, (int) $this->app->repo(FederativeEntity::class)->find($federativeEntity->id)->status);
    }
}
