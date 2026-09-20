<?php

namespace Tests\ConectaEnte;

use Tests\Abstract\TestCase;
use Tests\Traits\UserDirector;

class PanelNavTest extends TestCase
{
    use UserDirector;

    function testAcrescentaEntesFederadosAoGrupoAdmin()
    {
        $nav = $this->aplicarHook();

        $this->assertContains('conectaente/federativeEntities', array_column($nav['admin']['items'], 'route'));
    }

    function testNaoAcrescentaItemSemDestino()
    {
        $nav = $this->aplicarHook();

        foreach ($nav['admin']['items'] as $item) {
            $this->assertStringNotContainsString('#', $item['route']);
        }
    }

    function testNaoAlteraOGrupoMore()
    {
        $nav = $this->aplicarHook();

        $this->assertArrayNotHasKey('condition', $nav['more']);
        $this->assertContains('panel/my-account', array_column($nav['more']['items'], 'route'));
    }

    function testEntesFederadosSoApareceParaSaasSuperAdmin()
    {
        $this->login($this->userDirector->createUser());
        $condicao = $this->itemEntesFederados($this->aplicarHook())['condition'];
        $this->assertFalse($condicao());

        $this->login($this->userDirector->createUser(['saasSuperAdmin']));
        $condicao = $this->itemEntesFederados($this->aplicarHook())['condition'];
        $this->assertTrue($condicao());
    }

    private function aplicarHook(): array
    {
        $nav = [
            'more' => ['items' => [['route' => 'panel/my-account']]],
            'admin' => ['items' => []],
        ];

        $this->app->applyHook('panel.nav', [&$nav]);

        return $nav;
    }

    private function itemEntesFederados(array $nav): array
    {
        foreach ($nav['admin']['items'] as $item) {
            if ($item['route'] === 'conectaente/federativeEntities') {
                return $item;
            }
        }

        $this->fail('O item "Entes Federados" não foi acrescentado ao menu.');
    }
}
