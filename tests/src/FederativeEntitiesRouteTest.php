<?php

namespace Tests\ConectaEnte;

use Tests\Abstract\TestCase;
use Tests\Traits\RequestFactory;
use Tests\Traits\UserDirector;

class FederativeEntitiesRouteTest extends TestCase
{
    use RequestFactory;
    use UserDirector;

    function testVisitanteNaoAcessa()
    {
        $this->assertStatus401($this->requestFactory->GET('conectaente', 'federativeEntities'));
    }

    function testUsuarioComumNaoAcessa()
    {
        $this->login($this->userDirector->createUser());

        $this->assertStatus403($this->requestFactory->GET('conectaente', 'federativeEntities'));
    }

    function testSaasSuperAdminAcessa()
    {
        $this->login($this->userDirector->createUser(['saasSuperAdmin']));

        $this->assertStatus200($this->requestFactory->GET('conectaente', 'federativeEntities'));
    }
}
