<?php

namespace Tests\ConectaEnte;

use MapasCulturais\Entities\Opportunity;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

class OpportunityRequirementsRouteTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    function testGuestIsAskedToLogIn()
    {
        $this->assertStatus401($this->requestFactory->GET('conectaente', 'opportunityRequirements', [1]));
    }

    function testUserWhoCannotEditTheOpportunityIsRefused()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);
        $this->login($this->userDirector->createUser());

        $this->assertSame(403, $this->send($this->requirements($opportunity->id)));
    }

    function testMissingOpportunityIsNotFound()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertSame(404, $this->send($this->requirements(999999)));
    }

    function testUnsealedOpportunityHasNothingToReport()
    {
        $opportunity = $this->coreCompleteOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);
        $opportunity->shortDescription = '';
        $opportunity->save(true);

        $this->assertSame(200, $this->send($this->requirements($opportunity->id)));

        $this->assertSame(['sealed' => false, 'missing' => [], 'labels' => []], $this->responseJson());
    }

    function testSealedIncompleteOpportunityListsThePluginAndTheCoreFields()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);
        $opportunity->shortDescription = '';
        $opportunity->vacancies = 0;
        $opportunity->save(true);

        $this->assertSame(200, $this->send($this->requirements($opportunity->id)));

        $response = $this->responseJson();
        $this->assertTrue($response['sealed']);
        $this->assertEqualsCanonicalizing(['shortDescription', 'vacancies', 'conectaente_executionType'], array_keys($response['missing']));
        $this->assertEquals([
            'shortDescription' => 'Descrição Curta',
            'vacancies' => 'Total de vagas',
            'conectaente_executionType' => 'Tipo de Edital',
        ], $response['labels']);
    }

    function testSealedCompleteOpportunityHasNothingMissing()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);

        $this->assertSame(200, $this->send($this->requirements($opportunity->id)));

        $this->assertSame(['sealed' => true, 'missing' => [], 'labels' => []], $this->responseJson());
    }

    function testRouteAnswersWhatThePublicationRefuses()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);

        $this->send($this->requirements($opportunity->id));
        $missing = $this->responseJson()['missing'];
        $this->assertSame(400, $this->send($this->requestFactory->POST('opportunity', 'publish', [$opportunity->id])));

        $this->assertSame($this->responseJson()['data'], $missing);
    }

    function testFieldsWithoutARegisteredLabelGetTheCoreScreenText()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->getFile('rules')->delete(true);
        $opportunity->terms = ['area' => []];
        $opportunity->registrationProponentTypes = [];
        $opportunity->registrationRanges = [['label' => 'Faixa única', 'limit' => 1, 'value' => 1]];
        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => [['tipo' => 'email', 'descricao' => 'secretaria']]];
        $opportunity->save(true);

        $this->send($this->requirements($opportunity->id));

        $this->assertEquals([
            'rules' => 'Regulamento',
            'term-area' => 'Área de Interesse',
            'registrationProponentTypes' => 'Tipos do proponente',
            'registrationRangesVacancies' => 'Faixas/linhas',
            'registrationRangesTotalResource' => 'Faixas/linhas',
            'conectaente_registrationChannelsEmail' => 'Formas de inscrição previstas no edital',
        ], $this->responseJson()['labels']);
    }

    function testKeyWithoutAnyLabelIsShownAsItself()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $isActive = true;
        // desligado no fim, porque hook não sai da App
        $this->app->hook('entity(Opportunity).validationErrors', function (&$errors) use (&$isActive) {
            if ($isActive) {
                $errors['themeField'] = ['Campo exigido pelo tema.'];
            }
        });

        try {
            $this->send($this->requirements($opportunity->id));
        } finally {
            $isActive = false;
        }

        $this->assertSame(['themeField' => 'themeField'], $this->responseJson()['labels']);
    }

    private function requirements(int $opportunityId): ServerRequestInterface
    {
        return $this->requestFactory->GET('conectaente', 'opportunityRequirements', [$opportunityId], ajax: true);
    }

    private function responseJson(): array
    {
        return json_decode((string) $this->app->response->getBody(), true);
    }
}
