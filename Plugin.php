<?php

namespace ConectaEnte;

use ConectaEnte\Controllers\ConectaEnteController;
use MapasCulturais\i;
use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin
{

    public function _init(){
        $app = App::i();

        $app->hook('panel.nav', function (&$nav) use ($app) {
            if (isset($nav['admin']['items'])) {
                $nav['admin']['items'][] = [
                    'route' => 'conectaente/federativeEntities',
                    'icon' => 'agent',
                    'label' => i::__('Entes Federados'),
                    'condition' => fn() => $app->user->is('saasSuperAdmin'),
                ];
            }
        });
    }

    function register(){
        $app = App::i();

        $app->registerController('conectaente', ConectaEnteController::class);
    }
}
