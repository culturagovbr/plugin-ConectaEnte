<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use Tests\Abstract\TestCase;
use Tests\Traits\RequestFactory;

/**
 * A faixa que avisa, em qualquer página, que as requisições ao CultBR estão simuladas.
 */
class DevModeBannerTest extends TestCase
{
    use RequestFactory;

    const BANNER = 'conectaente-dev-mode-banner';

    function testTheBannerDoesNotExistInLiveMode()
    {
        Plugin::instance()->mode = Plugin::MODE_LIVE;

        $this->assertStringNotContainsString(
            self::BANNER,
            $this->homePage(),
            'Faixa de simulação em produção é pior do que faixa nenhuma: diria ao gestor que nada do que ele vê é real.',
        );
    }

    function testTheBannerShowsUpInDevMode()
    {
        Plugin::instance()->mode = Plugin::MODE_DEV;

        $page = $this->homePage();

        $this->assertStringContainsString(self::BANNER, $page);
        $this->assertStringContainsString('As requisições ao CultBR são simuladas', $page);
    }

    private function homePage(): string
    {
        $this->app->run($this->requestFactory->GET('site', 'index'), false);
        $page = (string) $this->app->response->getBody();

        // sem esta pré-condição, uma página de erro passaria nos dois testes
        $this->assertStringContainsString('main-header', $page, 'A página precisa ter renderizado para a faixa poder ser procurada.');

        return $page;
    }

    // o Plugin é singleton do processo: o modo trocado aqui vazaria para os testes seguintes
    protected function tearDown(): void
    {
        Plugin::instance()->mode = null;

        parent::tearDown();
    }
}
