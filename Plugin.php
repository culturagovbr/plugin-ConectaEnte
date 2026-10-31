<?php

namespace ConectaEnte;

use ConectaEnte\Repositories\FederativeEntityRepository;
use ConectaEnte\Services\FederativeEntityService;
use ConectaEnte\Services\OpportunitySealGuard;
use Doctrine\ORM\Events;
use MapasCulturais\App;
use MapasCulturais\Entities\OpportunitySealRelation;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Entities\SealRelation;
use MapasCulturais\Exceptions\PermissionDenied;

class Plugin extends \MapasCulturais\Plugin
{
    public function _init()
    {
        $app = App::i();

        $this->hookSingleEntityPerOpportunity($app);

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

            $repository = new FederativeEntityRepository();
            $entities = $repository->findAll();

            // o token nunca vai para a tela: só o indicador e o final dele, pela mesma regra da API
            $service = new FederativeEntityService($repository, function () {
                return true;
            });
            $tokens = [];
            foreach ($entities as $entity) {
                $tokens[$entity->getId()] = $service->present($entity);
            }

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
                'tokens' => $tokens,
                'activeSeals' => $seals,
                'usedSealIds' => array_map(function ($entity) {
                    return $entity->getSealId();
                }, $entities),
                'simulated' => !Config::isLive(),
            ]);
        });
    }

    /**
     * Um ente por oportunidade (#44).
     */
    private function hookSingleEntityPerOpportunity(App $app)
    {
        // defesa de todos os caminhos que gravam a relação; o core não traduz o BadRequest em resposta HTTP
        $app->em->getEventManager()->addEventListener([Events::prePersist], new Listeners\SingleEntityPerOpportunityListener());

        // a tela de selos da oportunidade chama esta ação: aqui o motivo chega ao usuário como 400 em JSON
        $app->hook('POST(opportunity.createSealRelation):before', function () use ($app) {
            if (empty($this->urlData['id']) || !isset($this->postData['sealId'])) {
                return;
            }

            $opportunity = $this->repository->find($this->urlData['id']);
            $seal = $app->repo('Seal')->find($this->postData['sealId']);

            // sem permissão sobre a oportunidade e sobre o selo (o core exige as duas), quem responde é
            // o core (403): nada do ente vaza antes
            if (!$opportunity || !$seal || !$opportunity->canUser('createSealRelation') || !$seal->canUser('@control')) {
                return;
            }

            $message = Plugin::sealConflictMessage($opportunity, $seal);

            if ($message !== null) {
                $this->errorJson($message, 400);
            }
        });
    }

    /**
     * @param \MapasCulturais\Entities\Opportunity $opportunity
     * @param Seal                                  $seal
     * @param SealRelation|null                     $ignored     a própria relação, quando já existe
     *
     * @return string|null
     */
    public static function sealConflictMessage($opportunity, $seal, SealRelation $ignored = null)
    {
        $app = App::i();
        $relations = $app->repo('OpportunitySealRelation')->findBy(['owner' => $opportunity]);

        // relações agendadas e ainda sem flush (`createSealRelation($seal, true, false)`) não estão no banco
        foreach ($app->em->getUnitOfWork()->getScheduledEntityInsertions() as $scheduled) {
            if ($scheduled instanceof OpportunitySealRelation && $scheduled->owner === $opportunity) {
                $relations[] = $scheduled;
            }
        }

        $applied = [];

        foreach ($relations as $relation) {
            if ($relation !== $ignored) {
                $applied[] = [
                    'seal_id' => $relation->seal->id,
                    'status' => $relation->status,
                    'seal_status' => $relation->seal->status,
                ];
            }
        }

        return (new OpportunitySealGuard(new FederativeEntityRepository()))->conflict($seal->id, $applied);
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
