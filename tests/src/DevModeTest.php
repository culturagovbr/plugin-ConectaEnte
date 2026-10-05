<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Transport\FixtureTransport;
use Tests\ConectaEnte\Doubles\FakeTransport;
use ConectaEnte\Plugin;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\CapturesLog;

/**
 * Em modo dev nenhuma rota do CultBR é chamada: a resposta vem de `fixtures/<rota>.json`.
 */
class DevModeTest extends TestCase
{
    use CapturesLog;

    protected function tearDown(): void
    {
        $this->popCapturedLog();

        parent::tearDown();
    }

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

    function testDevIsTheDefaultWhileTheCultBrDoesNotSettle()
    {
        $this->assertTrue(Plugin::instance()->isDevMode(), 'A instalação declara CONECTAENTE_MODE=live para falar com o CultBR de verdade.');
    }

    // o env() do core testa `isset`, e string vazia está setada: o default nunca entra, e '' não é 'dev'
    function testAnEmptyModeFallsBackToDevInsteadOfLive()
    {
        $this->assertSame(Plugin::MODE_DEV, $this->modeFor(''), 'Modo vazio não pode virar envio real para o host default, que é homologação.');
    }

    function testOnlyLiveTurnsOnTheRealTransport()
    {
        $this->assertSame(Plugin::MODE_LIVE, $this->modeFor(Plugin::MODE_LIVE));
        $this->assertSame(Plugin::MODE_LIVE, $this->modeFor('  LIVE  '), 'Caixa e espaço são erro de digitação, não outra intenção.');
        $this->assertSame(Plugin::MODE_DEV, $this->modeFor('  dev  '), 'Idem para o lado do dev.');
    }

    function testAnUnknownModeFallsBackToDevInsteadOfLive()
    {
        foreach (['development', 'producao', 'LIVEE', '0'] as $declared) {
            $this->assertSame(Plugin::MODE_DEV, $this->modeFor($declared), "O modo \"{$declared}\" não é `live`, então não envia nada.");
        }
    }

    // medido no container: o env() do core devolve float para `1`, bool para `true` e bool para `false`
    function testAModeThatIsNotAStringStillFallsBackToDev()
    {
        foreach ([1.0, true, false, 0] as $declared) {
            $this->assertSame(
                Plugin::MODE_DEV,
                $this->modeFor($declared),
                'Sem o cast para string, um valor que o env() converteu quebraria a comparação em tempo de execução.',
            );
        }
    }

    function testAnUnknownModeSaysSoInTheLog()
    {
        $plugin = Plugin::instance();
        $log = $this->captureLog();
        $plugin->mode = 'development';

        try {
            $plugin->warnOnUnknownMode();
        } finally {
            $plugin->mode = null;
        }

        $this->assertTrue(
            $log->hasWarningThatContains('development'),
            'Cair em dev é o lado seguro, mas sem aviso a instalação não descobre que declarou errado.',
        );
    }

    function testAKnownModeDoesNotWarn()
    {
        $plugin = Plugin::instance();
        $log = $this->captureLog();

        foreach ([Plugin::MODE_DEV, Plugin::MODE_LIVE, '  LIVE  '] as $declared) {
            $plugin->mode = $declared;

            try {
                $plugin->warnOnUnknownMode();
            } finally {
                $plugin->mode = null;
            }
        }

        $this->assertFalse($log->hasWarningRecords(), 'Avisar sobre modo válido treinaria o operador a ignorar o aviso.');
    }

    // sem isto, tirar a chamada do _init() nao quebra teste nenhum e o aviso nunca acontece no boot.
    // Premissa: o aviso e a PRIMEIRA linha do _init(), antes do registerJobType que relanca contra o singleton da suite
    function testTheBootWarnsAboutAnUnknownMode()
    {
        $log = $this->captureLog();

        try {
            new Plugin(['mode' => 'development']);
        } catch (\Exception $e) {
            $this->assertStringContainsString('already registered', $e->getMessage(), 'Premissa do teste: a exceção esperada é a do job type, não outra falha.');
        }

        $this->assertTrue(
            $log->hasWarningThatContains('development'),
            'O aviso tem que sair no boot da instalação, não só quando alguém chama o método.',
        );
    }

    private function modeFor(mixed $declared): string
    {
        $plugin = Plugin::instance();
        $plugin->mode = $declared;

        try {
            return $plugin->mode();
        } finally {
            $plugin->mode = null;
        }
    }

    function testTheEnteFilterHoldsWithARealTransport()
    {
        $plugin = Plugin::instance();
        $plugin->transport = FakeTransport::replying(200, ['data' => [['cnpj' => '99999999999999', 'exercicios' => [['id' => 'de-outro-ente']]]]]);

        try {
            $result = $plugin->client()->getParInformation('token', '12200176000176');
        } finally {
            $plugin->transport = null;
        }

        $this->assertNull($result->tree->exercises[0] ?? null, 'Em modo dev a fixture serve a qualquer ente; um transporte de verdade continua conferindo o cnpj.');
    }

    private function fixtureTransport(): FixtureTransport
    {
        return new FixtureTransport(Plugin::instance()->fixturesPath());
    }
}
