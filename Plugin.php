<?php

namespace ConectaEnte;

use ConectaEnte\Repositories\FederativeEntityRepository;
use MapasCulturais\App;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Exceptions\PermissionDenied;

class Plugin extends \MapasCulturais\Plugin
{
    public function _init()
    {
        $app = App::i();

        $app->log->debug('[ConectaEnte] _init() mode=' . Config::mode());

        // o menu do painel não tem API: o callback escreve o <li> direto
        $app->hook('panel.menu:after', function () use ($app) {
            if (!Plugin::canManage()) {
                return;
            }

            $active = $this->template == 'panel/federative-entities' ? 'active' : '';
            $url = $app->createUrl('panel', 'federative-entities');

            echo "<li><a class='$active' href='$url' rel='noopener noreferrer'><span class='icon icon-seal'></span> Entes Federados</a></li>";
        });

        $app->hook('GET(panel.federative-entities)', function () use ($app) {
            $this->requireAuthentication();

            if (!Plugin::canManage()) {
                throw new PermissionDenied($app->user, null, 'view');
            }

            $app->view->enqueueStyle('app', 'conectaente-federative-entities', 'css/conecta-ente/federative-entities.css');
            $app->view->enqueueScript('app', 'conectaente-federative-entities', 'js/conecta-ente/federative-entities.js', ['mapasculturais']);

            $entities = (new FederativeEntityRepository())->findAll();

            $seals = [];
            foreach ($app->repo('Seal')->findBy(['status' => Seal::STATUS_ENABLED], ['name' => 'ASC']) as $seal) {
                $seals[$seal->id] = $seal->name;
            }

            // o selo de um ente pode ter saído da lista de ativos (arquivado, rascunho): o nome ainda aparece
            foreach ($entities as $entity) {
                if (!isset($seals[$entity->getSealId()])) {
                    $seal = $app->repo('Seal')->find($entity->getSealId());
                    $seals[$entity->getSealId()] = $seal ? $seal->name : '#' . $entity->getSealId();
                }
            }

            $this->render('federative-entities', [
                'entities' => $entities,
                'activeSeals' => $seals,
                'usedSealIds' => array_map(function ($entity) {
                    return $entity->getSealId();
                }, $entities),
                'simulated' => !Config::isLive(),
            ]);
        });
    }

    public function register()
    {
        $app = App::i();

        $app->registerController('conecta-ente', Controllers\ConectaEnteController::class);

        $app->log->debug('[ConectaEnte] register()');
    }

    public static function canManage()
    {
        $user = App::i()->user;

        return !$user->is('guest') && $user->is('saasSuperAdmin');
    }
}
