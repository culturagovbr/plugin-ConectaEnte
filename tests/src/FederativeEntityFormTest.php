<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Plugin;
use MapasCulturais\Entities\Seal;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;
use Tests\Traits\RequestFactory;

class FederativeEntityFormTest extends TestCase
{
    use ConectaEnteFixtures;
    use RequestFactory;

    const DOCUMENT = '12200176000176';

    protected function tearDown(): void
    {
        Plugin::instance()->transport = null;

        parent::tearDown();
    }

    function testRegistersTheEntityWithTheDocumentTheApiReturns()
    {
        $this->loginAsSaasSuperAdmin();
        $this->apiAccepts();
        $seal = $this->createSeal();

        $request = $this->requestFactory->POST('conectaente', 'federativeEntities', [], [
            'name' => 'Municipio de Arapiraca',
            'sealId' => $seal->id,
            'token' => 'um-token',
        ]);

        $this->assertStatus200($request);

        $federativeEntity = $this->app->repo(FederativeEntity::class)->findOneBy(['document' => self::DOCUMENT]);
        $this->assertSame('Municipio de Arapiraca', $federativeEntity->name);
        $this->assertNotNull($this->app->repo(FederativeEntitySeal::class)->findOneBySeal($seal));
    }

    function testRejectedTokenRegistersNothing()
    {
        $this->loginAsSaasSuperAdmin();
        Plugin::instance()->transport = FakeTransport::replying(401, ['detail' => 'Token expirado']);

        $request = $this->requestFactory->POST('conectaente', 'federativeEntities', [], [
            'name' => 'Municipio de Arapiraca',
            'sealId' => $this->createSeal()->id,
            'token' => 'um-token',
        ]);

        $this->assertStatus400($request);
        $this->assertSame([], $this->app->repo(FederativeEntity::class)->findAll());
    }

    function testUnavailableApiAnswersDifferentlyFromRejection()
    {
        $this->loginAsSaasSuperAdmin();
        Plugin::instance()->transport = FakeTransport::unreachable();

        $request = $this->requestFactory->POST('conectaente', 'federativeEntities', [], [
            'name' => 'Municipio de Arapiraca',
            'sealId' => $this->createSeal()->id,
            'token' => 'um-token',
        ]);

        $this->assertHttpStatus($request, 503);
    }

    function testDocumentAlreadyRegisteredIsRefused()
    {
        $this->loginAsSaasSuperAdmin();
        $this->apiAccepts();
        $this->createFederativeEntity('Cadastrado antes', self::DOCUMENT);

        $request = $this->requestFactory->POST('conectaente', 'federativeEntities', [], [
            'name' => 'Outro nome',
            'sealId' => $this->createSeal()->id,
            'token' => 'um-token',
        ]);

        $this->assertStatus400($request);
        $this->assertStringNotContainsString('Cadastrado antes', (string) $this->app->response->getBody());
        $this->assertCount(1, $this->app->repo(FederativeEntity::class)->findAll());
    }

    function testSealAlreadyUsedByAnotherEntityIsRefused()
    {
        $this->loginAsSaasSuperAdmin();
        $this->apiAccepts();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal, 'Dono do selo', '99999999000199');

        $request = $this->requestFactory->POST('conectaente', 'federativeEntities', [], [
            'name' => 'Municipio de Arapiraca',
            'sealId' => $seal->id,
            'token' => 'um-token',
        ]);

        $this->assertStatus400($request);
    }

    function testSealWithValidityIsRefusedOnRegistration()
    {
        $this->loginAsSaasSuperAdmin();
        $this->apiAccepts();

        $request = $this->requestFactory->POST('conectaente', 'federativeEntities', [], [
            'name' => 'Municipio de Arapiraca',
            'sealId' => $this->createSealWithValidity(12)->id,
            'token' => 'um-token',
        ]);

        $this->assertStatus400($request);
        $this->assertStringContainsString('12 meses', json_decode((string) $this->app->response->getBody(), true)['data']['seal'][0]);
        $this->assertCount(0, $this->app->repo(FederativeEntity::class)->findAll());
    }

    function testSealWithValidityIsRefusedWhenRegisteringTheSeal()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        $seal = $this->createSealWithValidity(6);

        $request = $this->requestFactory->POST('conectaente', 'federativeEntitySeal', [$federativeEntity->id], ['sealId' => $seal->id]);

        $this->assertStatus400($request);
        $this->assertNull($this->app->repo(FederativeEntitySeal::class)->findOneBySeal($seal));
    }

    function testRegistersTheSealOfAnEntityThatHasNone()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        $seal = $this->createSeal();

        $request = $this->requestFactory->POST('conectaente', 'federativeEntitySeal', [$federativeEntity->id], [
            'sealId' => $seal->id,
        ]);

        $this->assertStatus200($request);
        $this->assertNotNull($this->app->repo(FederativeEntitySeal::class)->findOneBySeal($seal));
    }

    function testSealRequestWithoutSealIsRefused()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();

        $request = $this->requestFactory->POST('conectaente', 'federativeEntitySeal', [$federativeEntity->id], []);

        $this->assertStatus400($request);
    }

    function testSealOfAnotherEntityIsRefusedWhenRegisteringTheSealOfAnEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal, 'Dono do selo', '99999999000199');
        $federativeEntity = $this->createFederativeEntity('Municipio de Arapiraca', '12200176000176');

        $request = $this->requestFactory->POST('conectaente', 'federativeEntitySeal', [$federativeEntity->id], [
            'sealId' => $seal->id,
        ]);

        $this->assertStatus400($request);
        $this->assertNull($this->app->repo(FederativeEntitySeal::class)->findOneByFederativeEntity($federativeEntity));
    }

    function testSecondSealForTheSameEntityIsRefused()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntityWithSeal($this->createSeal());
        $anotherSeal = $this->createSeal();

        $request = $this->requestFactory->POST('conectaente', 'federativeEntitySeal', [$federativeEntity->id], [
            'sealId' => $anotherSeal->id,
        ]);

        $this->assertStatus400($request);
        $this->assertNull($this->app->repo(FederativeEntitySeal::class)->findOneBySeal($anotherSeal));
    }

    function testRemovesTheSealOfAnEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntityWithSeal($this->createSeal());

        $request = $this->requestFactory->DELETE('conectaente', 'federativeEntitySeal', [$federativeEntity->id]);

        $this->assertStatus200($request);
        $this->assertNull($this->app->repo(FederativeEntitySeal::class)->findOneByFederativeEntity($federativeEntity));
    }

    function testRemovingTheSealKeepsTheSealItself()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);

        $this->assertStatus200($this->requestFactory->DELETE('conectaente', 'federativeEntitySeal', [$federativeEntity->id]));

        $this->assertNotNull($this->app->repo(Seal::class)->find($seal->id));
    }

    function testRegularUserCannotRemoveTheSeal()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntityWithSeal($this->createSeal());

        $this->login($this->userDirector->createUser());

        $request = $this->requestFactory->DELETE('conectaente', 'federativeEntitySeal', [$federativeEntity->id]);

        $this->assertStatus403($request);
        $this->assertNotNull($this->app->repo(FederativeEntitySeal::class)->findOneByFederativeEntity($federativeEntity));
    }

    function testRegularUserIsRefusedBeforeTheSealIsLookedUp()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();

        $this->login($this->userDirector->createUser());

        $request = $this->requestFactory->DELETE('conectaente', 'federativeEntitySeal', [$federativeEntity->id]);

        $this->assertStatus403($request);
    }

    function testRevealsTheTokenAfterThePasswordIsConfirmed()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');
        $federativeEntity = $this->createFederativeEntity('Municipio de Arapiraca', self::DOCUMENT, 'token-secreto');

        $request = $this->requestFactory->POST('conectaente', 'federativeEntityToken', [$federativeEntity->id], ['password' => 'segredo']);

        $this->assertSame(['token' => 'token-secreto'], $this->responseBody($request));
    }

    function testWrongPasswordDoesNotRevealTheToken()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');
        $federativeEntity = $this->createFederativeEntity('Municipio de Arapiraca', self::DOCUMENT, 'token-secreto');

        $request = $this->requestFactory->POST('conectaente', 'federativeEntityToken', [$federativeEntity->id], ['password' => 'outra']);

        $this->assertStatus403($request);
        $this->assertStringNotContainsString('token-secreto', (string) $this->app->response->getBody());
    }

    function testAdminWithoutLocalPasswordCannotRevealTheToken()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity('Municipio de Arapiraca', self::DOCUMENT, 'token-secreto');

        $request = $this->requestFactory->POST('conectaente', 'federativeEntityToken', [$federativeEntity->id], ['password' => 'qualquer']);

        $this->assertStatus400($request);
        $this->assertStringNotContainsString('token-secreto', (string) $this->app->response->getBody());
    }

    function testRegularUserCannotRevealTheToken()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity('Municipio de Arapiraca', self::DOCUMENT, 'token-secreto');

        $this->login($this->userDirector->createUser());

        $request = $this->requestFactory->POST('conectaente', 'federativeEntityToken', [$federativeEntity->id], ['password' => 'qualquer']);

        $this->assertStatus403($request);
        $this->assertStringNotContainsString('token-secreto', (string) $this->app->response->getBody());
    }

    function testTokenOfUnknownEntityIsNotFound()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');

        $this->assertStatus404($this->requestFactory->POST('conectaente', 'federativeEntityToken', [999999], ['password' => 'segredo']));
    }

    function testReplacesTheToken()
    {
        $this->loginAsSaasSuperAdmin();
        $this->apiAccepts();
        $federativeEntity = $this->createFederativeEntity('Municipio de Arapiraca', self::DOCUMENT, 'token-antigo');

        $request = $this->requestFactory->PATCH('conectaente', 'federativeEntity', [$federativeEntity->id], [
            'token' => 'token-novo',
        ]);

        $this->assertStatus200($request);
        $this->app->em->clear();
        $this->assertSame('token-novo', $this->app->repo(FederativeEntity::class)->find($federativeEntity->id)->token);
    }

    function testEmptyTokenKeepsTheStoredOne()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity('Municipio de Arapiraca', self::DOCUMENT, 'token-antigo');

        $request = $this->requestFactory->PATCH('conectaente', 'federativeEntity', [$federativeEntity->id], ['token' => '']);

        $this->assertStatus200($request);
        $this->app->em->clear();
        $this->assertSame('token-antigo', $this->app->repo(FederativeEntity::class)->find($federativeEntity->id)->token);
    }

    function testTokenOfAnotherDocumentIsRefused()
    {
        $this->loginAsSaasSuperAdmin();
        $this->apiAccepts('99999999000199');
        $federativeEntity = $this->createFederativeEntity('Municipio de Arapiraca', self::DOCUMENT, 'token-antigo');

        $request = $this->requestFactory->PATCH('conectaente', 'federativeEntity', [$federativeEntity->id], [
            'token' => 'token-de-outro-ente',
        ]);

        $this->assertStatus400($request);
        $this->assertStringNotContainsString('99999999000199', (string) $this->app->response->getBody());
        $this->app->em->clear();
        $this->assertSame('token-antigo', $this->app->repo(FederativeEntity::class)->find($federativeEntity->id)->token);
    }

    function testRegularUserCannotRegister()
    {
        $this->login($this->userDirector->createUser());
        $this->apiAccepts();

        $request = $this->requestFactory->POST('conectaente', 'federativeEntities', [], [
            'name' => 'Municipio de Arapiraca',
            'sealId' => $this->createSeal()->id,
            'token' => 'um-token',
        ]);

        $this->assertStatus403($request);
    }

    private function apiAccepts(string $document = self::DOCUMENT): void
    {
        Plugin::instance()->transport = FakeTransport::replying(200, [
            'valido' => true,
            'tipo' => 'SISTEMA',
            'cnpj' => $document,
            'nome_ente' => 'MUNICIPIO DE ARAPIRACA',
        ]);
    }

    private function responseBody($request): array
    {
        $this->app->reset();
        $this->app->run($request, false);

        return json_decode((string) $this->app->response->getBody(), true) ?? [];
    }

    private function assertHttpStatus($request, int $code): void
    {
        $this->app->reset();
        $this->app->run($request, false);

        $this->assertSame($code, $this->app->response->getStatusCode());
    }
}
