<?php

namespace Tests\ConectaEnte;

use Tests\Abstract\TestCase;
use Tests\Traits\UserDirector;

class PanelNavTest extends TestCase
{
    use UserDirector;

    function testAddsFederativeEntitiesToTheAdminGroup()
    {
        $nav = $this->applyPanelNavHook();

        $this->assertContains('conectaente/federativeEntities', array_column($nav['admin']['items'], 'route'));
    }

    function testDoesNotAddItemWithoutDestination()
    {
        $nav = $this->applyPanelNavHook();

        foreach ($nav['admin']['items'] as $item) {
            $this->assertStringNotContainsString('#', $item['route']);
        }
    }

    function testDoesNotChangeTheMoreGroup()
    {
        $nav = $this->applyPanelNavHook();

        $this->assertArrayNotHasKey('condition', $nav['more']);
        $this->assertContains('panel/my-account', array_column($nav['more']['items'], 'route'));
    }

    function testFederativeEntitiesIsVisibleOnlyToSaasSuperAdmin()
    {
        $this->login($this->userDirector->createUser());
        $condition = $this->federativeEntitiesItem($this->applyPanelNavHook())['condition'];
        $this->assertFalse($condition());

        $this->login($this->userDirector->createUser(['saasSuperAdmin']));
        $condition = $this->federativeEntitiesItem($this->applyPanelNavHook())['condition'];
        $this->assertTrue($condition());
    }

    private function applyPanelNavHook(): array
    {
        $nav = [
            'more' => ['items' => [['route' => 'panel/my-account']]],
            'admin' => ['items' => []],
        ];

        $this->app->applyHook('panel.nav', [&$nav]);

        return $nav;
    }

    private function federativeEntitiesItem(array $nav): array
    {
        foreach ($nav['admin']['items'] as $item) {
            if ($item['route'] === 'conectaente/federativeEntities') {
                return $item;
            }
        }

        $this->fail('O item "Entes Federados" não foi acrescentado ao menu.');
    }
}
