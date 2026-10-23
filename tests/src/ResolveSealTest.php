<?php

namespace Tests\ConectaEnte;

use MapasCulturais\Entities\Seal;
use MapasCulturais\Entities\SealRelation;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;

class ResolveSealTest extends TestCase
{
    use ConectaEnteFixtures;

    function testRegisteredSealResolvesFederativeEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        $this->assertSame($federativeEntity->id, $this->resolveFederativeEntity($opportunity)->id);
    }

    function testUnregisteredSealResolvesNoFederativeEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunityWithSeal($this->createSeal());

        $this->assertNull($this->resolveFederativeEntity($opportunity));
    }

    function testOpportunityWithoutSealResolvesNoFederativeEntity()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertNull($this->resolveFederativeEntity($this->createOpportunity()));
    }

    function testPendingSealRelationDoesNotCount()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        $relation = $opportunity->getSealRelations()[0];
        $relation->status = SealRelation::STATUS_PENDING;
        $relation->save(true);

        $this->assertNull($this->resolveFederativeEntity($opportunity));
    }

    function testTrashedSealDoesNotCount()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        $seal->status = Seal::STATUS_TRASH;
        $seal->save(true);

        $this->assertNull($this->resolveFederativeEntity($opportunity));
    }
}
