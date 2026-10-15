<?php

namespace ConectaEnte;

use MapasCulturais\App;

/**
 * Plugin ConectaEnte (v5).
 *
 * Ciclo de vida na v5: o construtor de Module chama _init() e, depois,
 * App::register() chama register() de cada plugin. Não existem isPlugin()
 * nem preInit() (shim vazio) — qualquer lógica de preInit() da v7 vive em _init().
 *
 * Código compatível com PHP 7.2: sem enum, readonly, match, argumentos
 * nomeados, propriedades tipadas nem first-class callable.
 */
class Plugin extends \MapasCulturais\Plugin
{
    /**
     * Hooks e registro de job type (issues #8, #12, #13).
     */
    public function _init()
    {
        $app = App::i();

        $app->log->debug('[ConectaEnte] _init() mode=' . Config::mode());
    }

    /**
     * Controller e metadados (issues #6, #9).
     */
    public function register()
    {
        $app = App::i();

        $app->log->debug('[ConectaEnte] register()');
    }
}
