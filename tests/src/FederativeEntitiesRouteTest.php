<?php

namespace Tests\ConectaEnte;

use Tests\Abstract\TestCase;
use Tests\Traits\RequestFactory;
use Tests\Traits\UserDirector;

class FederativeEntitiesRouteTest extends TestCase
{
    use RequestFactory;
    use UserDirector;

    function testGuestCannotAccess()
    {
        $this->assertStatus401($this->requestFactory->GET('conectaente', 'federativeEntities'));
    }

    function testRegularUserCannotAccess()
    {
        $this->login($this->userDirector->createUser());

        $this->assertStatus403($this->requestFactory->GET('conectaente', 'federativeEntities'));
    }

    function testSaasSuperAdminCanAccess()
    {
        $this->login($this->userDirector->createUser(['saasSuperAdmin']));

        $this->assertStatus200($this->requestFactory->GET('conectaente', 'federativeEntities'));
    }
}
