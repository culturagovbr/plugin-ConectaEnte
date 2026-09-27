<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Services\RequiredCoreFields;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

/**
 * Na edição da oportunidade selada, os campos que a publicação exige chegam à tela marcados como obrigatórios.
 */
class RequiredLabelTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    function testSealedOpportunityMarksTheFieldsThePublicationDemands()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT);

        $description = $this->entityDescription($this->editPage($opportunity));

        $this->assertTrue($description['vacancies']['required'], 'Vagas é exigido na publicação e precisa chegar marcado.');
        $this->assertTrue($description['totalResource']['required'], 'Valor total é exigido na publicação e precisa chegar marcado.');
    }

    function testOpportunityWithoutSealKeepsWhatTheCoreSays()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $description = $this->entityDescription($this->editPage($opportunity));

        $this->assertFalse($description['vacancies']['required'], 'Sem selo, vagas segue como o core o define.');
        $this->assertFalse($description['totalResource']['required'], 'Sem selo, valor total segue como o core o define.');
    }

    function testTheMarkDoesNotLeakToTheNextOpportunityInTheSameProcess()
    {
        $this->loginAsSaasSuperAdmin();
        $sealed = $this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT);
        $unsealed = $this->createOpportunity();

        $this->editPage($sealed);
        $description = $this->entityDescription($this->editPage($unsealed));

        $this->assertFalse($description['vacancies']['required'], 'Em produção cada requisição é um processo; aqui as duas correm no mesmo, e a marca da selada não pode vazar.');
        $this->assertFalse($description['totalResource']['required']);
    }

    function testTheMarkedFieldsAreTheOnesTheCoreLeavesOptional()
    {
        $this->assertSame(['vacancies', 'totalResource'], RequiredCoreFields::FIELDS);
    }

    function testAnotherPageOfTheSealedOpportunityIsNotMarked()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunityWithSeal($this->federativeSeal(), Opportunity::STATUS_DRAFT);

        $this->assertSame(200, $this->send($this->requestFactory->GET('opportunity', 'single', [$opportunity->id])));
        $description = $this->entityDescription((string) $this->app->response->getBody());

        $this->assertFalse($description['vacancies']['required'], 'A marca é da tela de edição; a página pública segue o core.');
        $this->assertFalse($description['totalResource']['required']);
    }

    function testFieldsTheCoreAlreadyMarksAreLeftAlone()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $description = $this->entityDescription($this->editPage($opportunity));

        foreach (['shortDescription', 'registrationFrom', 'registrationTo', 'registrationProponentTypes'] as $field) {
            $this->assertTrue($description[$field]['required'], "O core já exige {$field} pela coluna não-nula: o plugin não precisa marcar.");
        }
    }


    private function entityDescription(string $page): array
    {
        return $this->jsObject($page)['EntitiesDescription']['opportunity'];
    }
}
