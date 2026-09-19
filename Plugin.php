<?php

namespace ConectaEnte;

use ConectaEnte\Controllers\ConectaEnteController;
use MapasCulturais\i;
use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin
{

    public function _init(){
        $app = App::i();

        $app->hook('panel.nav', function (&$nav) {

            $nav['more']['condition'] = fn() => App::i()->user->is('saasSuperAdmin');

            if (isset($nav['admin']['items'])) {
                $adminCondition = $nav['admin']['condition'] ?? fn() => true;
                $nav['admin']['condition'] = function () use ($adminCondition) {
                    if (App::i()->user->is('saasSuperAdmin')) {
                        return true;
                    }

                    return is_callable($adminCondition) ? $adminCondition() : (bool) $adminCondition;
                };

                $nav['admin']['items'][] = [
                    'route' => 'conectaEnte/federativeEntities',
                    'icon' => 'agent',
                    'label' => i::__('Entes Federados'),
                    'condition' => fn() => App::i()->user->is('saasSuperAdmin'),
                ];

                $nav['admin']['items'][] = [
                    'route' => 'panel/opportunitiesSync',
                    'icon' => 'sync',
                    'label' => i::__('Sincronização'),
                    'condition' => fn() => App::i()->user->is('saasSuperAdmin'),
                ];
            }
        });
    }

    function register(){
        $app = App::i();

        $app->registerController('conectaEnte', ConectaEnteController::class);
    }
}
