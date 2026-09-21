<?php

namespace Tests\ConectaEnte;

use MapasCulturais\Exceptions\BadRequest;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;
use Tests\Traits\RequestFactory;

class OneSealPerOpportunityTest extends TestCase
{
    use ConectaEnteFixtures;
    use RequestFactory;

    function testSecondFederativeEntitySealIsRejected()
    {
        $this->loginAsSaasSuperAdmin();
        $firstSeal = $this->createSeal();
        $secondSeal = $this->createSeal();
        $this->createFederativeEntityWithSeal($firstSeal, 'Governo de Santa Catarina', '11111111000191');
        $this->createFederativeEntityWithSeal($secondSeal, 'Governo do Paraná', '22222222000192');
        $opportunity = $this->createOpportunityWithSeal($firstSeal);

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Governo de Santa Catarina');

        $opportunity->createSealRelation($secondSeal);
    }

    function testRouteRejectsWithBadRequestInsteadOfServerError()
    {
        $this->loginAsSaasSuperAdmin();
        $firstSeal = $this->createSeal();
        $secondSeal = $this->createSeal();
        $this->createFederativeEntityWithSeal($firstSeal, 'Governo de Santa Catarina', '11111111000191');
        $this->createFederativeEntityWithSeal($secondSeal, 'Governo do Paraná', '22222222000192');
        $opportunity = $this->createOpportunityWithSeal($firstSeal);

        $request = $this->requestFactory->POST('opportunity', 'createSealRelation', [$opportunity->id], ['sealId' => $secondSeal->id]);

        $this->assertStatus400($request, 'Aplicar um segundo selo de ente deve recusar com 400 e mensagem, não com erro de servidor.');
    }

    function testRouteChecksTheSealTheCoreWillApply()
    {
        $this->loginAsSaasSuperAdmin();
        $firstSeal = $this->createSeal();
        $secondSeal = $this->createSeal();
        $regularSeal = $this->createSeal();
        $this->createFederativeEntityWithSeal($firstSeal, 'Governo de Santa Catarina', '11111111000191');
        $this->createFederativeEntityWithSeal($secondSeal, 'Governo do Paraná', '22222222000192');
        $opportunity = $this->createOpportunityWithSeal($firstSeal);

        $request = $this->requestFactory->POST(
            'opportunity',
            'createSealRelation',
            [$opportunity->id],
            payload: ['sealId' => $regularSeal->id],
            query_params: ['sealId' => $secondSeal->id]
        );

        $this->assertStatus400($request, 'O core aplica o selo da query string; a verificação precisa olhar o mesmo.');
    }

    function testRouteWithoutSealDoesNotBreak()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunityWithSeal($this->createSeal());

        $request = $this->requestFactory->POST('opportunity', 'createSealRelation', [$opportunity->id], []);

        $this->assertStatus404($request, 'Sem selo informado o core desvia a rota; o plugin não pode explodir antes disso.');
    }

    function testRouteAcceptsRegularSeal()
    {
        $this->loginAsSaasSuperAdmin();
        $registeredSeal = $this->createSeal();
        $regularSeal = $this->createSeal();
        $this->createFederativeEntityWithSeal($registeredSeal);
        $opportunity = $this->createOpportunityWithSeal($registeredSeal);

        $request = $this->requestFactory->POST('opportunity', 'createSealRelation', [$opportunity->id], ['sealId' => $regularSeal->id]);

        $this->assertStatus200($request);
    }

    function testExistingSealRelationCanStillBeSaved()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);

        $relation = $opportunity->getSealRelations()[0];
        $relation->renovationRequest = true;
        $relation->save(true);

        $this->assertTrue($relation->renovationRequest);
    }

    function testRegularSealIsStillAccepted()
    {
        $this->loginAsSaasSuperAdmin();
        $registeredSeal = $this->createSeal();
        $regularSeal = $this->createSeal();
        $this->createFederativeEntityWithSeal($registeredSeal);
        $opportunity = $this->createOpportunityWithSeal($registeredSeal);

        $opportunity->createSealRelation($regularSeal);

        $this->assertCount(2, $opportunity->getSealRelations());
    }

    function testReplacingTheFederativeEntitySealWorks()
    {
        $this->loginAsSaasSuperAdmin();
        $previousSeal = $this->createSeal();
        $newSeal = $this->createSeal();
        $this->createFederativeEntityWithSeal($previousSeal, 'Governo de Santa Catarina', '11111111000191');
        $federativeEntity = $this->createFederativeEntityWithSeal($newSeal, 'Governo do Paraná', '22222222000192');
        $opportunity = $this->createOpportunityWithSeal($previousSeal);

        $opportunity->removeSealRelation($previousSeal);
        $opportunity->createSealRelation($newSeal);

        $this->assertSame($federativeEntity->id, $this->resolveFederativeEntity($opportunity)->id);
    }
}
