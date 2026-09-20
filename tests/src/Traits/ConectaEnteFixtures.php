<?php

namespace Tests\ConectaEnte\Traits;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Seal;
use Tests\Traits\OpportunityBuilder;
use Tests\Traits\SealDirector;
use Tests\Traits\UserDirector;

trait ConectaEnteFixtures
{
    use OpportunityBuilder;
    use SealDirector;
    use UserDirector;

    protected function loginAsSaasSuperAdmin(): void
    {
        $this->login($this->userDirector->createUser(['saasSuperAdmin']));
    }

    protected function createSeal(): Seal
    {
        return $this->sealDirector->createSeal(disable_access_control: true);
    }

    protected function createFederativeEntity(string $name = 'Governo de Santa Catarina', string $document = '12345678000190', ?string $token = null): FederativeEntity
    {
        $federativeEntity = new FederativeEntity;
        $federativeEntity->name = $name;
        $federativeEntity->document = $document;
        $federativeEntity->token = $token ?? uniqid('token-');
        $federativeEntity->save(true);

        return $federativeEntity;
    }

    protected function linkSeal(FederativeEntity $federativeEntity, Seal $seal): FederativeEntitySeal
    {
        $sealLink = new FederativeEntitySeal;
        $sealLink->federativeEntity = $federativeEntity;
        $sealLink->seal = $seal;
        $sealLink->save(true);

        return $sealLink;
    }

    protected function createFederativeEntityWithSeal(Seal $seal, string $name = 'Governo de Santa Catarina', string $document = '12345678000190'): FederativeEntity
    {
        $federativeEntity = $this->createFederativeEntity($name, $document);
        $this->linkSeal($federativeEntity, $seal);

        return $federativeEntity;
    }

    protected function createOpportunity(): Opportunity
    {
        $agent = $this->app->user->profile;

        $this->opportunityBuilder->reset($agent, $agent)->fillRequiredProperties()->firstPhase()->save()->done();

        return $this->opportunityBuilder->getInstance();
    }

    protected function createOpportunityWithSeal(Seal $seal): Opportunity
    {
        $opportunity = $this->createOpportunity();
        $opportunity->createSealRelation($seal);

        return $opportunity;
    }

    /**
     * Ente resolvido a partir dos selos concedidos da oportunidade, relendo do banco.
     */
    protected function resolveFederativeEntity(Opportunity $opportunity): ?FederativeEntity
    {
        $this->app->em->clear();

        $opportunity = $this->app->repo(Opportunity::class)->find($opportunity->id);

        return $this->app->repo(FederativeEntitySeal::class)->findOneByOpportunity($opportunity)?->federativeEntity;
    }
}
