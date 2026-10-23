<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Client;
use ConectaEnte\Plugin;
use Tests\Abstract\TestCase;

class SmokeTest extends TestCase
{
    function testPluginIsEnabled()
    {
        $this->assertArrayHasKey('ConectaEnte', $this->app->plugins);
        $this->assertInstanceOf(Plugin::class, $this->app->plugins['ConectaEnte']);
    }

    function testFallsBackToTheHomologationHost()
    {
        $plugin = $this->app->plugins['ConectaEnte'];

        $this->assertSame(Plugin::DEFAULT_HOST, $plugin->getConfig()['host']);
        $this->assertInstanceOf(Client::class, $plugin->client());
    }
}
