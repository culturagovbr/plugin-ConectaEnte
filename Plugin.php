<?php

namespace ConectaEnte;

use ConectaEnte\Auth\PasswordWindow;
use ConectaEnte\Controllers\ConectaEnteController;
use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Http\Client;
use ConectaEnte\Http\Transport\TransportInterface;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Services\PublicationContext;
use ConectaEnte\Services\PublicationRequirements;
use ConectaEnte\Services\PublicationStamp;
use ConectaEnte\Services\SealedOpportunity;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Exceptions\BadRequest;
use MapasCulturais\i;
use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin
{
    const DEFAULT_HOST = 'https://ente.conecta.hmg.cultbr.cultura.gov.br';
    const DEFAULT_PASSWORD_WINDOW = 120;

    function __construct(array $config = [])
    {
        $config += [
            'host' => env('CONECTAENTE_HOST', self::DEFAULT_HOST),
            'passwordWindow' => (int) env('CONECTAENTE_PASSWORD_WINDOW', self::DEFAULT_PASSWORD_WINDOW),
        ];

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

    /** Janela alternativa, para os testes controlarem a duração. */
    public ?PasswordWindow $passwordWindow = null;

    function passwordWindow(): PasswordWindow
    {
        return $this->passwordWindow ?? new PasswordWindow($this->_config['passwordWindow']);
    }

    function sealedOpportunity(): SealedOpportunity
    {
        return new SealedOpportunity(App::i()->repo(FederativeEntitySeal::class));
    }

    private ?PublicationStamp $publicationStamp = null;

    // uma instância por requisição: ela guarda a marca da duplicação em curso
    function publicationStamp(): PublicationStamp
    {
        return $this->publicationStamp ??= new PublicationStamp($this->sealedOpportunity());
    }

    private ?PublicationContext $publicationContext = null;

    // uma instância por requisição: ela guarda o status pedido pela requisição em curso
    function publicationContext(): PublicationContext
    {
        return $this->publicationContext ??= new PublicationContext();
    }

    function publicationRequirements(): PublicationRequirements
    {
        return new PublicationRequirements($this->publicationStamp());
    }

    public function _init(){
        $app = App::i();

        // BaseV1 imprime o grupo `app`, BaseV2 o `app-v2`
        $app->view->enqueueStyle('app', 'conectaente', 'css/conectaente.css');
        $app->view->enqueueStyle('app-v2', 'conectaente', 'css/conectaente.css');

        // o core religa o $this dos hooks ao objeto que os dispara
        $app->hook('auth.logout:before', fn() => Plugin::instance()->passwordWindow()->close());

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

        // metadado alterado aqui ainda entra no saveMetadata do mesmo save
        $app->hook('entity(Opportunity).save:before', function () {
            Plugin::instance()->publicationStamp()->stamp($this);
        });

        // a cópia é salva antes e depois de receber os metadados: a marca dura a requisição inteira
        $app->hook('ALL(opportunity.duplicate):before', function () {
            Plugin::instance()->publicationStamp()->duplicationStarted($this->requestedEntity);
        });
        $app->hook('mapasculturais.run:after', fn() => Plugin::instance()->publicationStamp()->duplicationFinished());

        // o publish e o PUT validam antes de mudar o status: a regra olha o status pedido
        $app->hook('ALL(opportunity.publish):before', function () {
            if ($this->requestedEntity) {
                Plugin::instance()->publicationContext()->enter($this->requestedEntity);
            }
        });
        $app->hook('PUT(opportunity.single):before', function () {
            if ($this->requestedEntity && isset($this->postData['status'])) {
                Plugin::instance()->publicationContext()->enter($this->requestedEntity, (int) $this->postData['status']);
            }
        });
        $app->hook('PATCH(opportunity.single):before', function () {
            if ($this->requestedEntity) {
                Plugin::instance()->publicationContext()->patching($this->requestedEntity);
            }
        });
        $app->hook('mapasculturais.run:after', fn() => Plugin::instance()->publicationContext()->leave());

        $app->hook('template(opportunity.edit.tabs):end', function () {
            $this->part('conectaente/opportunity-tab');
        });

        // por último: o tema registra seus hooks depois do plugin, e os erros dele também ficam no PATCH
        $app->hook('entity(Opportunity).validationErrors', function (&$errors) {
            Plugin::instance()->requirePublicationFields($this, $errors);
        }, 1000);
    }

    /**
     * Soma aos erros da oportunidade selada o que falta para ela sair publicada da requisição.
     */
    function requirePublicationFields(Opportunity $opportunity, array &$errors): void
    {
        if (!$this->publicationContext()->endsPublished($opportunity) || !$this->sealedOpportunity()->isSealed($opportunity)) {
            return;
        }

        $errors = array_merge_recursive($errors, $this->publicationRequirements()->missing($opportunity));

        if ($errors && $this->publicationContext()->isPatching($opportunity)) {
            $this->keepErrorsInPatch($errors);
        }
    }

    // o PATCH do core só devolve erro de chave que veio no corpo, e publicar por ele não passa pelo publish
    private function keepErrorsInPatch(array &$errors): void
    {
        $controller = App::i()->controller('opportunity');

        if (isset($controller->postData['status'])) {
            $pending = count($errors);
            $errors['status'][] = sprintf(i::_n(
                'A oportunidade não pode ser publicada: falta %d campo.',
                'A oportunidade não pode ser publicada: faltam %d campos.',
                $pending,
            ), $pending);
        }

        foreach (array_keys($errors) as $key) {
            $controller->postData[$key] ??= null;
        }
    }

    static function sealConflictMessage(FederativeEntity $federativeEntity): string
    {
        return sprintf(i::__('Esta oportunidade já usa o selo do Ente Federado %s.'), $federativeEntity->name);
    }

    function register(){
        $app = App::i();

        $app->registerController('conectaente', ConectaEnteController::class);

        CultBrMetadata::register($this);
    }
}
