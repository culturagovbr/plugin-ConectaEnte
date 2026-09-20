<?php

namespace Tests\ConectaEnte;

use MapasCulturais\Entities\Seal;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;
use Tests\Traits\RequestFactory;

class FederativeEntitiesListTest extends TestCase
{
    use ConectaEnteFixtures;
    use RequestFactory;

    function testListsRegisteredFederativeEntities()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntityWithSeal($this->createSeal(), 'Governo de Santa Catarina', '11111111000191');
        $this->createFederativeEntityWithSeal($this->createSeal(), 'Governo do Paraná', '22222222000192');

        $names = array_column($this->listedEntities(), 'name');

        $this->assertContains('Governo de Santa Catarina', $names);
        $this->assertContains('Governo do Paraná', $names);
    }

    function testShowsTheSealOfEachFederativeEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);

        [$entity] = $this->listedEntities();

        $this->assertSame([['id' => $seal->id, 'name' => $seal->name, 'usable' => true]], $entity['seals']);
    }

    function testFormatsTheDocument()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntity('Governo de Santa Catarina', '12345678000190');

        $this->assertSame('12.345.678/0001-90', $this->listedEntities()[0]['document']);
    }

    function testMarksSealThatIsNoLongerUsable()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);

        $seal->status = Seal::STATUS_TRASH;
        $seal->save(true);

        $this->assertFalse($this->listedEntities()[0]['seals'][0]['usable']);
    }

    function testEntityWithoutSealComesWithAnEmptySealList()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntity();

        $this->assertSame([], $this->listedEntities()[0]['seals']);
    }

    function testNeverSendsTheToken()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntity('Governo de Santa Catarina', '12345678000190', 'token-que-nao-pode-vazar');

        $this->assertStringNotContainsString('token-que-nao-pode-vazar', $this->renderList());
        $this->assertArrayNotHasKey('token', $this->listedEntities()[0]);
    }

    function testSendsAnEmptyListWhenThereIsNothingRegistered()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertSame([], $this->listedEntities());
    }

    /**
     * Os entes chegam ao componente por prop, então é o JSON da prop que carrega o contrato da tela.
     */
    private function listedEntities(): array
    {
        preg_match("/:entities='([^']*)'/", $this->renderList(), $matches);

        return json_decode($matches[1] ?? '[]', true) ?? [];
    }

    private function renderList(): string
    {
        $this->app->reset();
        $this->app->run($this->requestFactory->GET('conectaente', 'federativeEntities'), false);

        $body = $this->app->response->getBody();
        $body->rewind();

        return (string) $body;
    }
}
