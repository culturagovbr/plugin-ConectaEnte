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

        // Configura o menu do painel: renomeia "Minhas Oportunidades" e move para "Ente Federado"
        $app->hook('panel.nav', function (&$nav) use ($canAccess) {

            // "Meus aplicativos" visível apenas para saasSuperAdmin
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

            // Usuário sem canAccess não vê "Minhas Oportunidades" (apenas GestorCultBr pode criar/acessar a página)
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

            // Só manipula os menus para GestorCultBr, se não for, parar aqui
            if (!UserAccessService::isGestorCultBr()) {
                return;
            }

            if (UserAccessService::isSaasSuperAdmin()) {
                return;
            }

            // Remove o menu "Admin" para GestorCultBr
            $nav['admin']['condition'] = fn() => false;

            // Remove o menu "Minhas Oportunidades" do grupo original
            foreach ($nav['opportunities']['items'] as $key => $item) {
                if (isset($item['route']) && $item['route'] === 'panel/opportunities') {
                    $nav['opportunities']['items'][$key]['condition'] = fn() => false;
                }
            }

            // Remove o menu "Minhas Validações" do grupo "Editais e Oportunidades" (opportunities)
            if (isset($nav['opportunities']['items'])) {
                foreach ($nav['opportunities']['items'] as $key => $item) {
                    if (isset($item['route']) && $item['route'] === 'panel/validations') {
                        $nav['opportunities']['items'][$key]['condition'] = fn() => false;
                    }
                }
            }

            // Criando menus específicos para GestorCultBr
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

        /**
         * Registra o papel de Gestor CultBR
         */
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
