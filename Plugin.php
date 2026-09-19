<?php

namespace ConectaEnte;

use AldirBlanc\Services\UserAccessService;
use MapasCulturais\i;
use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin
{

    public function _init(){
        $app = App::i();

        $canAccess = UserAccessService::canAccess();

        $app->hook('panel.nav', function (&$nav) use ($canAccess) {

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
                    'route' => 'panel/federativeEntities',
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

            if (!$canAccess && isset($nav['opportunities']['items'])) {
                foreach ($nav['opportunities']['items'] as $key => $item) {
                    if (isset($item['route']) && $item['route'] === 'panel/opportunities') {
                        $nav['opportunities']['items'][$key]['condition'] = fn() => false;
                    }
                }
            }

            foreach ($nav as &$group) {
                if (isset($group['items'])) {
                    foreach ($group['items'] as &$item) {
                        if (isset($item['route'])) {
                            if (in_array($item['route'], ['panel/submissions', 'panel/registrations', 'submissions', 'registrations'])) {
                                $item['icon'] = 'registration';
                            } elseif (in_array($item['route'], ['panel/evaluations', 'evaluations'])) {
                                $item['icon'] = 'evaluation';
                            } elseif (in_array($item['route'], ['panel/validations', 'validations'])) {
                                $item['icon'] = 'validation';
                            }
                        }
                    }
                    unset($item);
                }
            }
            unset($group);

            if (!UserAccessService::isGestorCultBr()) {
                return;
            }

            if (UserAccessService::isSaasSuperAdmin()) {
                return;
            }

            $nav['admin']['condition'] = fn() => false;

            foreach ($nav['opportunities']['items'] as $key => $item) {
                if (isset($item['route']) && $item['route'] === 'panel/opportunities') {
                    $nav['opportunities']['items'][$key]['condition'] = fn() => false;
                }
            }

            if (isset($nav['opportunities']['items'])) {
                foreach ($nav['opportunities']['items'] as $key => $item) {
                    if (isset($item['route']) && $item['route'] === 'panel/validations') {
                        $nav['opportunities']['items'][$key]['condition'] = fn() => false;
                    }
                }
            }

            $nav['federativeEntity'] = [
                'condition' => fn() => true,
                'label' => i::__('Ente Federado'),
                'items' => [
                    [
                        'route' => 'panel/opportunities',
                        'icon' => 'opportunity',
                        'label' => i::__('Oportunidades'),
                    ],
                    [
                        'route' => 'panel/federativeEntityAgents',
                        'icon' => 'agent',
                        'label' => i::__('Minha Equipe'),
                    ],
                    [
                        'route' => 'panel/validations',
                        'icon' => 'validation',
                        'label' => i::__('Minhas Validações'),
                    ]
                ],
            ];
        });
    }

    function register(){
        $app = App::i();

        $def = new \MapasCulturais\Definitions\Role(
            'GestorCultBr',
            i::__('Gestor CultBR'),
            i::__('Gestor CultBR'),
            true,
            function (\MapasCulturais\UserInterface $user, $subsite_id) {
                return false;
            },
            [],
        );
        $app->registerRole($def);
    }
}
