<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Auth\PasswordWindow;
use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Plugin;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;
use Tests\Traits\RequestFactory;

class PasswordWindowTest extends TestCase
{
    use ConectaEnteFixtures;
    use RequestFactory;

    protected function setUp(): void
    {
        parent::setUp();

        unset($_SESSION[PasswordWindow::SESSION_KEY]);
    }

    protected function tearDown(): void
    {
        unset($_SESSION[PasswordWindow::SESSION_KEY]);
        Plugin::instance()->passwordWindow = null;

        parent::tearDown();
    }

    function testFirstActionWithoutPasswordAsksForIt()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');
        $federativeEntity = $this->createFederativeEntity('Municipio de Arapiraca', '12200176000176', 'token-secreto');

        $this->assertStatus401($this->tokenRequest($federativeEntity));
        $this->assertStringNotContainsString('token-secreto', (string) $this->app->response->getBody());
    }

    function testConfirmedPasswordOpensTheWindowForTheNextActions()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');
        $federativeEntity = $this->createFederativeEntity();

        $this->assertStatus200($this->tokenRequest($federativeEntity, 'segredo'));
        $this->assertStatus200($this->tokenRequest($federativeEntity));
        $this->assertStatus200($this->requestFactory->DELETE('conectaente', 'federativeEntity', [$federativeEntity->id]));

        $this->app->em->clear();
        $this->assertSame(FederativeEntity::STATUS_TRASH, (int) $this->app->repo(FederativeEntity::class)->find($federativeEntity->id)->status);
    }

    function testWindowExpiresAfterTheConfiguredSeconds()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');
        $federativeEntity = $this->createFederativeEntity();

        $_SESSION[PasswordWindow::SESSION_KEY] = time() - Plugin::DEFAULT_PASSWORD_WINDOW - 1;

        $this->assertStatus401($this->tokenRequest($federativeEntity));
    }

    function testWrongPasswordDoesNotOpenTheWindow()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');
        $federativeEntity = $this->createFederativeEntity();

        $this->assertStatus403($this->tokenRequest($federativeEntity, 'outra'));
        $this->assertStatus401($this->tokenRequest($federativeEntity));
    }

    function testWindowOfZeroSecondsAlwaysAsksForThePassword()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');
        $federativeEntity = $this->createFederativeEntity();
        Plugin::instance()->passwordWindow = new PasswordWindow(0);

        $this->assertStatus200($this->tokenRequest($federativeEntity, 'segredo'));
        $this->assertStatus401($this->tokenRequest($federativeEntity));
    }

    function testAnotherSessionAsksAgain()
    {
        $this->loginAsSaasSuperAdminWithPassword('segredo');
        $federativeEntity = $this->createFederativeEntity();
        $this->assertStatus200($this->tokenRequest($federativeEntity, 'segredo'));

        unset($_SESSION[PasswordWindow::SESSION_KEY]);

        $this->assertStatus401($this->tokenRequest($federativeEntity));
    }

    function testLogoutClosesTheWindow()
    {
        $user = $this->loginAsSaasSuperAdminWithPassword('segredo');
        $federativeEntity = $this->createFederativeEntity();
        $this->assertStatus200($this->tokenRequest($federativeEntity, 'segredo'));

        $this->app->applyHookBoundTo($this->app->auth, 'auth.logout:before', [$user]);

        $this->assertStatus401($this->tokenRequest($federativeEntity));
    }

    private function tokenRequest(FederativeEntity $federativeEntity, ?string $password = null)
    {
        $payload = $password === null ? [] : ['password' => $password];

        return $this->requestFactory->POST('conectaente', 'federativeEntityToken', [$federativeEntity->id], $payload);
    }
}
