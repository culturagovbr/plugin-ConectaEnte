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

        $names = array_column($this->panelProp('entities'), 'name');

        $this->assertContains('Governo de Santa Catarina', $names);
        $this->assertContains('Governo do Paraná', $names);
    }

    function testShowsTheSealOfEachFederativeEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);

        [$entity] = $this->panelProp('entities');

        $this->assertSame([[
            'id' => $seal->id,
            'name' => $seal->name,
            'usable' => true,
            'files' => ['avatar' => null],
        ]], $entity['seals']);
    }

    function testFormatsTheDocument()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntity('Governo de Santa Catarina', '12345678000190');

        $this->assertSame('12.345.678/0001-90', $this->panelProp('entities')[0]['document']);
    }

    function testMarksSealThatIsNoLongerUsable()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);

        $seal->status = Seal::STATUS_TRASH;
        $seal->save(true);

        $this->assertFalse($this->panelProp('entities')[0]['seals'][0]['usable']);
    }

    function testEntityWithoutSealComesWithAnEmptySealList()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntity();

        $this->assertSame([], $this->panelProp('entities')[0]['seals']);
    }

    function testSendsTheTokenMaskedByTheServer()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntity('Governo de Santa Catarina', '12345678000190', 'token-que-nao-pode-vazar');

        $this->assertStringNotContainsString('token-que-nao-pode-vazar', $this->renderPanel());
        $this->assertSame('token-' . str_repeat('*', strlen('que-nao-pode-vazar')), $this->panelProp('entities')[0]['token']);
    }

    function testSendsAnEmptyListWhenThereIsNothingRegistered()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertSame([], $this->panelProp('entities'));
    }
}
