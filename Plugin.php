<?php

namespace ConectaEnte;

use AldirBlanc\Services\UserAccessService;
use ConectaEnte\Controllers\ConectaEnteController;
use MapasCulturais\i;
use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin
{

    public function _init(){
        $app = App::i();

        $app->hook('panel.nav', function (&$nav) {

            $nav['more']['condition'] = fn() => UserAccessService::isSaasSuperAdmin();

            if (isset($nav['admin']['items'])) {
                $adminCondition = $nav['admin']['condition'] ?? fn() => true;
                $nav['admin']['condition'] = function () use ($adminCondition) {
                    if (UserAccessService::isSaasSuperAdmin()) {
                        return true;
                    }

                    return is_callable($adminCondition) ? $adminCondition() : (bool) $adminCondition;
                };

                $nav['admin']['items'][] = [
                    'route' => 'conectaEnte/federativeEntities',
                    'icon' => 'agent',
                    'label' => i::__('Entes Federados'),
                    'condition' => fn() => UserAccessService::isSaasSuperAdmin(),
                ];

                $nav['admin']['items'][] = [
                    'route' => 'panel/opportunitiesSync',
                    'icon' => 'sync',
                    'label' => i::__('Sincronização'),
                    'condition' => fn() => UserAccessService::isSaasSuperAdmin(),
                ];
            }
        });
    }

    function register(){
        $app = App::i();

        $app->registerController('conectaEnte', ConectaEnteController::class);
    }
}
