<?php

namespace ConectaEnte;

use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin
{
    public function _init()
    {
        $app = App::i();

        $app->log->debug('[ConectaEnte] _init() mode=' . Config::mode());
    }

    public function register()
    {
        $app = App::i();

        $app->registerController('conecta-ente', Controllers\ConectaEnteController::class);

        $app->log->debug('[ConectaEnte] register()');
    }
}
