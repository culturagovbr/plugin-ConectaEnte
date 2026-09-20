<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use Tests\Abstract\TestCase;

class SmokeTest extends TestCase
{
    function testPluginEstaAtivo()
    {
        $this->assertArrayHasKey('ConectaEnte', $this->app->plugins);
        $this->assertInstanceOf(Plugin::class, $this->app->plugins['ConectaEnte']);
    }
}
