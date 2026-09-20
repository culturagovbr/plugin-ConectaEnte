<?php

namespace ConectaEnte;

use ConectaEnte\Controllers\ConectaEnteController;
use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Http\Client;
use ConectaEnte\Http\Transport\TransportInterface;
use ConectaEnte\Entities\FederativeEntitySeal;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Exceptions\BadRequest;
use MapasCulturais\i;
use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin
{
    const DEFAULT_HOST = 'https://ente.conecta.hmg.cultbr.cultura.gov.br';

    function __construct(array $config = [])
    {
        $config += ['host' => env('CONECTAENTE_HOST', self::DEFAULT_HOST)];

        parent::__construct($config);
    }

    static function instance(): self
    {
        return App::i()->plugins['ConectaEnte'];
    }

    /** Transporte alternativo, para os testes exercitarem as rotas sem chamar a API. */
    public ?TransportInterface $transport = null;

    function client(): Client
    {
        return new Client($this->_config['host'], $this->transport);
    }

    public function _init(){
        $app = App::i();

        // BaseV1 imprime o grupo `app` e BaseV2 o `app-v2`: registrar nos dois é o que faz o
        // estilo aparecer em qualquer tema.
        $app->view->enqueueStyle('app', 'conectaente', 'css/conectaente.css');
        $app->view->enqueueStyle('app-v2', 'conectaente', 'css/conectaente.css');

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

        $app->hook('entity(OpportunitySealRelation).save:before', function () use ($app) {
            $federativeEntity = $app->repo(FederativeEntitySeal::class)->findConflictingEntity($this->owner, $this->seal);

            if ($federativeEntity) {
                throw new BadRequest(Plugin::sealConflictMessage($federativeEntity));
            }
        });

        // O hook da entidade barra qualquer caminho, mas vira 500 sem mensagem: aqui a recusa volta como 400.
        $app->hook('POST(opportunity.createSealRelation):before', function () use ($app) {
            $opportunityId = $this->urlData['id'] ?? null;
            // O core lê o selo de `data`, onde a query string tem precedência sobre o corpo.
            $sealId = $this->data['sealId'] ?? null;

            if (!$opportunityId || !$sealId) {
                return;
            }

            $opportunity = $app->repo(Opportunity::class)->find($opportunityId);
            $seal = $app->repo(Seal::class)->find($sealId);

            if (!$opportunity || !$seal) {
                return;
            }

            $federativeEntity = $app->repo(FederativeEntitySeal::class)->findConflictingEntity($opportunity, $seal);

            if ($federativeEntity) {
                $this->errorJson(['sealId' => [Plugin::sealConflictMessage($federativeEntity)]], 400);
            }
        });
    }

    static function sealConflictMessage(FederativeEntity $federativeEntity): string
    {
        return sprintf(i::__('Esta oportunidade já usa o selo do ente federado %s.'), $federativeEntity->name);
    }

    function register(){
        $app = App::i();

        $app->registerController('conectaente', ConectaEnteController::class);
    }
}
