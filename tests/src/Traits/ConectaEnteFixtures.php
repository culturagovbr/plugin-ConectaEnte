<?php

namespace Tests\ConectaEnte\Traits;

use ConectaEnte\Auth\PasswordCheck;
use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Definitions\Metadata;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Entities\User;
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

    protected function loginAsSaasSuperAdminWithPassword(string $password): User
    {
        $user = $this->userDirector->createUser(['saasSuperAdmin']);

        if (!$this->app->getRegisteredMetadataByMetakey(PasswordCheck::PASSWORD_METADATA, User::class)) {
            $this->app->registerMetadata(new Metadata(PasswordCheck::PASSWORD_METADATA, ['label' => 'Senha']), User::class);
        }

        $this->app->disableAccessControl();
        $user->setMetadata(PasswordCheck::PASSWORD_METADATA, password_hash($password, PASSWORD_DEFAULT));
        $user->save(true);
        $this->app->enableAccessControl();

        $this->login($user);

        return $user;
    }

    // o director sorteia uma validade; selo de Ente Federado não tem
    protected function createSeal(): Seal
    {
        return $this->setSealValidity($this->sealDirector->createSeal(disable_access_control: true), 0);
    }

    protected function createSealWithValidity(int $months): Seal
    {
        return $this->setSealValidity($this->createSeal(), $months);
    }

    protected function setSealValidity(Seal $seal, int $months): Seal
    {
        $this->app->disableAccessControl();
        $seal->validPeriod = $months;
        $seal->save(true);
        $this->app->enableAccessControl();

        return $seal;
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

    protected function createOpportunity(int $status = Opportunity::STATUS_ENABLED): Opportunity
    {
        $agent = $this->app->user->profile;

        $this->opportunityBuilder->reset($agent, $agent, $status)->fillRequiredProperties()->firstPhase()->save()->done();

        return $this->opportunityBuilder->getInstance();
    }

    protected function createOpportunityWithSeal(Seal $seal, int $status = Opportunity::STATUS_ENABLED): Opportunity
    {
        $opportunity = $this->createOpportunity($status);
        $opportunity->createSealRelation($seal);

        return $opportunity;
    }

    protected function renderPanel(): string
    {
        $this->app->reset();
        $this->app->run($this->requestFactory->GET('conectaente', 'federativeEntities'), false);

        $body = $this->app->response->getBody();
        $body->rewind();

        return (string) $body;
    }

    /**
     * Os entes chegam ao componente por prop, então é o JSON da prop que carrega o contrato da tela.
     */
    protected function panelProp(string $prop): array
    {
        preg_match("/:{$prop}='([^']*)'/", $this->renderPanel(), $matches);

        return json_decode($matches[1] ?? '[]', true) ?? [];
    }

    /**
     * Ente resolvido a partir dos selos concedidos da oportunidade, relendo do banco.
     */
    protected function resolveFederativeEntity(Opportunity $opportunity): ?FederativeEntity
    {
        return $this->app->repo(FederativeEntitySeal::class)->findOneByOpportunity($this->reloaded($opportunity))?->federativeEntity;
    }

    /**
     * Oportunidade relida do banco, sem o estado que ficou em memória.
     */
    protected function reloaded(Opportunity $opportunity): Opportunity
    {
        $this->app->em->clear();

        return $this->app->repo(Opportunity::class)->find($opportunity->id);
    }
}
