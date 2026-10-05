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

    // o override da suite declara CONECTAENTE_MODE=dev, entao afirmar o singleton provaria o declarado,
    // nao o default. Aqui a variavel sai do ambiente para o default aparecer
    function testDevIsTheDefaultWhileTheCultBrDoesNotSettle()
    {
        $declared = $_ENV['CONECTAENTE_MODE'] ?? null;
        unset($_ENV['CONECTAENTE_MODE']);

        try {
            $plugin = $this->pluginBuiltWith([]);
        } finally {
            if ($declared !== null) {
                $_ENV['CONECTAENTE_MODE'] = $declared;
            }
        }

        $this->assertSame(
            Plugin::MODE_DEV,
            $plugin->getConfig()['mode'],
            'Sem nada declarado, a instalação nasce em dev: é ela que declara CONECTAENTE_MODE=live para falar com o CultBR de verdade.',
        );
        $this->assertTrue(Plugin::instance()->isDevMode(), 'E a suíte roda em dev, agora por declaração do override e não por acidente.');
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

    // a mutacao "defaultTransport() devolve sempre FixtureTransport" faria uma instalacao live
    // simular em silencio, e nenhum edital chegaria ao CultBR. `.invalid` nunca resolve (RFC 2606)
    function testLiveResolvesTheNetworkTransport()
    {
        $plugin = $this->pluginBuiltWith(['mode' => Plugin::MODE_LIVE, 'host' => 'https://cultbr.invalid']);

        $this->assertFalse(
            $plugin->client()->isHealthy(),
            'Em live o transporte vai à rede: host inexistente falha, e o de fixture responderia 200 para /health.',
        );
    }

    function testDevResolvesTheFixtureTransportEvenWithAnUnreachableHost()
    {
        $plugin = $this->pluginBuiltWith(['mode' => Plugin::MODE_DEV, 'host' => 'https://cultbr.invalid']);

        $this->assertTrue(
            $plugin->client()->isHealthy(),
            'Em dev o host não é usado: a resposta sai de fixtures/health.json.',
        );
    }

    // trocar o `+=` por `=` no construtor descartaria em silencio host, modo e intervalos declarados
    function testTheConfigDeclaredByTheInstallationSurvivesTheDefaults()
    {
        $plugin = $this->pluginBuiltWith(['mode' => Plugin::MODE_LIVE, 'host' => 'https://cultbr.declarado']);

        $config = $plugin->getConfig();
        $this->assertSame(Plugin::MODE_LIVE, $config['mode'], 'O modo declarado pela instalação prevalece sobre o default.');
        $this->assertSame('https://cultbr.declarado', $config['host']);
        $this->assertSame(Plugin::DEFAULT_SEND_MAX_ATTEMPTS, $config['sendMaxAttempts'], 'E o que a instalação não declarou continua vindo do default.');
    }

    const BUILD_HOOK = 'module(ConectaEnte\Plugin).init:before';

    // o _init() relanca contra o singleton da suite; o hook do core entrega a instancia com o config
    // ja montado, porque ele dispara depois do `$this->_config = $config` e antes do _init()
    private function pluginBuiltWith(array $config): Plugin
    {
        $built = null;
        // o alvo e desligado por referencia, nao por clearHooks(): o clear() do core nao invalida
        // o _hookCache, entao um hook ja aplicado continua sendo chamado depois de removido
        $target = &$built;
        $this->app->hook(self::BUILD_HOOK, function () use (&$target) {
            if ($target !== false) {
                $target = $this;
            }
        });
        $launched = false;

        try {
            new Plugin($config);
        } catch (\Exception $error) {
            $launched = true;
            $this->assertStringContainsString('already registered', $error->getMessage(), 'Premissa do teste: a exceção esperada é a do job type.');
        }

        // o registro de job type vem antes dos hooks no _init(): se ele deixar de relancar, cada chamada
        // daqui passa a registrar os ~20 hooks do plugin de novo, e a suite suja em silencio
        $this->assertTrue($launched, 'Premissa do teste: o _init() precisa ter relançado antes de registrar hook nenhum.');
        $this->assertInstanceOf(Plugin::class, $built, 'Premissa do teste: o hook do core precisa ter entregado a instância.');
        $plugin = $built;
        $target = false;

        return $plugin;
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

    // sem passar por defaultTransport(), a mutacao "devolve sempre FixtureTransport" nao quebra nada
    // e uma instalacao live simularia em silencio. Aqui $transport fica null de proposito
    function testDevResolvesTheFixtureTransportWithoutTouchingTheNetwork()
    {
        $plugin = Plugin::instance();
        $plugin->mode = Plugin::MODE_DEV;
        $plugin->transport = null;

        try {
            $this->assertTrue(
                $plugin->client()->isHealthy(),
                'Em dev o /health vem de fixtures/health.json; um transporte de rede não responderia 200 aqui.',
            );
        } finally {
            $plugin->mode = null;
        }
    }

    function testDevServesAnyEnteAndLiveDoesNot()
    {
        $plugin = Plugin::instance();
        $plugin->transport = null;
        $plugin->mode = Plugin::MODE_DEV;

        try {
            $anotherEnte = $plugin->client()->getParInformation('token', '99999999999999');
        } finally {
            $plugin->mode = null;
        }

        $this->assertNotNull(
            $anotherEnte->tree->exercises[0] ?? null,
            'A fixture do PAR é uma só e precisa servir a qualquer ente cadastrado em dev.',
        );
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
