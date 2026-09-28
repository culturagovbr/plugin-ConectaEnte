<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Transport\FixtureTransport;
use ConectaEnte\Plugin;
use Tests\Abstract\TestCase;

/**
 * Em modo dev nenhuma rota do CultBR é chamada: a resposta vem de `fixtures/<rota>.json`.
 */
class DevModeTest extends TestCase
{
    function testEachRouteReadsTheFixtureOfItsOwnName()
    {
        $transport = $this->fixtureTransport();

        foreach (['/api/v1/par-information' => 'exercicios', '/api/v1/integracao/validar-token' => 'valido', '/health' => 'status'] as $path => $key) {
            $response = $transport->get("https://cultbr.exemplo{$path}");

            $this->assertSame(200, $response->status, "A rota {$path} tem fixture de nome igual ao último segmento.");
            $this->assertArrayHasKey($key, $response->decoded()['data'][0] ?? $response->decoded());
        }
    }

    function testRouteWithoutAFixtureFailsInsteadOfAnsweringEmpty()
    {
        $response = $this->fixtureTransport()->get('https://cultbr.exemplo/api/v1/rota-que-nao-existe');

        $this->assertFalse($response->reachedServer(), 'Fixture faltando não pode virar resposta vazia com ar de válida.');
        $this->assertStringContainsString('rota-que-nao-existe', (string) $response->transportError);
    }

    function testLiveIsTheDefault()
    {
        $this->assertFalse(Plugin::instance()->isDevMode(), 'Sem declaração da instalação, o plugin fala com o CultBR de verdade.');
    }

    private function fixtureTransport(): FixtureTransport
    {
        return new FixtureTransport(Plugin::instance()->fixturesPath());
    }
}
