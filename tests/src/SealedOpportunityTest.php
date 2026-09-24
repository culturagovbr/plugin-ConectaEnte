<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use ConectaEnte\Services\SealedOpportunity;
use MapasCulturais\Entities\SealRelation;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;

class SealedOpportunityTest extends TestCase
{
    use ConectaEnteFixtures;

    function testOpportunityWithoutSealIsNotSealed()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $this->assertFalse($this->sealedOpportunity()->isSealed($this->reloaded($opportunity)));
    }

    function testGrantedFederativeEntitySealMakesTheOpportunitySealed()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->reloaded($this->createOpportunityWithSeal($seal));

        $this->assertTrue($this->sealedOpportunity()->isSealed($opportunity));
        $this->assertSame($federativeEntity->id, $this->sealedOpportunity()->federativeEntityOf($opportunity)->id);
    }

    function testPendingSealRelationDoesNotSeal()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        $relation = $opportunity->getSealRelations()[0];
        $relation->status = SealRelation::STATUS_PENDING;
        $relation->save(true);

        $this->assertFalse($this->sealedOpportunity()->isSealed($this->reloaded($opportunity)));
    }

    function testTrashedFederativeEntityDoesNotSeal()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        $federativeEntity->delete(true);

        $this->assertFalse($this->sealedOpportunity()->isSealed($this->reloaded($opportunity)));
    }

    function testSealThatBelongsToNoFederativeEntityDoesNotSeal()
    {
        $this->loginAsSaasSuperAdmin();
        // outro Ente Federado no banco, para o filtro por selo ter o que excluir
        $this->createFederativeEntityWithSeal($this->createSeal());
        $opportunity = $this->createOpportunityWithSeal($this->createSeal());

        $this->assertFalse($this->sealedOpportunity()->isSealed($this->reloaded($opportunity)));
    }

    function testPhaseOfSealedOpportunityIsNotSealed()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        // sem o selo a fase já seria falsa, e a guarda de fase ficaria sem teste
        $phase = $opportunity->lastPhase;
        $phase->createSealRelation($seal);

        $this->assertTrue($this->sealedOpportunity()->isSealed($this->reloaded($opportunity)));
        $this->assertFalse($this->sealedOpportunity()->isSealed($this->reloaded($phase)));
    }

    private function sealedOpportunity(): SealedOpportunity
    {
        return Plugin::instance()->sealedOpportunity();
    }
}
