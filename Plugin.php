<?php

namespace ConectaEnte;

use ConectaEnte\Auth\PasswordWindow;
use ConectaEnte\Controllers\ConectaEnteController;
use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Http\Client;
use ConectaEnte\Http\Transport\TransportInterface;
use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Jobs\ParInformationSyncJob;
use ConectaEnte\Services\ParInformationService;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Exceptions\BadRequest;
use MapasCulturais\i;
use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin
{
    const DEFAULT_HOST = 'https://ente.conecta.hmg.cultbr.cultura.gov.br';
    const DEFAULT_PASSWORD_WINDOW = 120;
    // Maior que o intervalo do ParInformationSyncJob (30min), para nunca expirar entre execuções.
    const DEFAULT_PAR_INFORMATION_CACHE_TTL = 2700;

    function __construct(array $config = [])
    {
        $config += [
            'host' => env('CONECTAENTE_HOST', self::DEFAULT_HOST),
            'passwordWindow' => (int) env('CONECTAENTE_PASSWORD_WINDOW', self::DEFAULT_PASSWORD_WINDOW),
            'parInformationCacheTTL' => (int) env('CONECTAENTE_PAR_INFORMATION_CACHE_TTL', self::DEFAULT_PAR_INFORMATION_CACHE_TTL),
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

    /** Serviço alternativo, para os testes controlarem cache/transporte da árvore do PAR. */
    public ?ParInformationService $parInformationService = null;

    function parInformationService(): ParInformationService
    {
        return $this->parInformationService ?? new ParInformationService($this->_config['parInformationCacheTTL']);
    }

    public function _init(){
        $app = App::i();

        $app->registerJobType(new ParInformationSyncJob(ParInformationSyncJob::SLUG));

        // id do job é determinístico (ParInformationSyncJob::_generateId): chamado sem
        // condição a cada boot, mas vira só uma leitura por PK quando já está agendado.
        try {
            $app->enqueueJob(
                ParInformationSyncJob::SLUG,
                [],
                'now',
                ParInformationSyncJob::INTERVAL,
                ParInformationSyncJob::ITERATIONS,
            );
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            // tabela job ainda não criada (migração em andamento)
        }

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

        // O template core não tinha hook ali; foi adicionado um ponto novo para o card "Informações".
        $app->hook('template(opportunity.edit.opportunity-basic-info-information-fields):end', function () {
            /** @var \MapasCulturais\Themes\BaseV2\Theme $this */
            $this->import('conectaente--par-selector');
            echo '<conectaente--par-selector :entity="entity"></conectaente--par-selector>';
        });

        // Não bloqueia seleção parcial nem publicação: só recusa uma cadeia de ids que não
        // existe na árvore do ente (ex. atividade de outra ação), quando os 4 estão presentes.
        $app->hook('entity(Opportunity).validationErrors', function (&$errors) use ($app) {
            /** @var Opportunity $this */
            if ($this->parent) {
                return;
            }

            $ids = array_filter([
                'parExercicioId' => (string) ($this->parExercicioId ?? ''),
                'parMetaId' => (string) ($this->parMetaId ?? ''),
                'parAcaoId' => (string) ($this->parAcaoId ?? ''),
                'parAtividadeId' => (string) ($this->parAtividadeId ?? ''),
            ], fn($value) => $value !== '');

            if (count($ids) !== 4) {
                return;
            }

            $result = Plugin::instance()->parInformationService()->getForOpportunity($this);

            if (!$result || !$result->tree) {
                return;
            }

            if (!$result->tree->isConsistentPath($ids['parExercicioId'], $ids['parMetaId'], $ids['parAcaoId'], $ids['parAtividadeId'])) {
                $errors['parAtividadeId'] = [i::__('A seleção do PAR não forma uma cadeia válida (exercício/meta/ação/atividade).')];
            }
        });
    }

    static function sealConflictMessage(FederativeEntity $federativeEntity): string
    {
        return sprintf(i::__('Esta oportunidade já usa o selo do Ente Federado %s.'), $federativeEntity->name);
    }

    function register(){
        $app = App::i();

        $app->registerController('conectaente', ConectaEnteController::class);

        // Mesmas chaves que o AldirBlanc/Pnab já usam para o PAR: não ativar os dois fluxos ao mesmo tempo.
        foreach (['parExercicioId' => 'Exercício', 'parMetaId' => 'Meta', 'parAcaoId' => 'Ação', 'parAtividadeId' => 'Atividade'] as $key => $label) {
            $this->registerMetadata('MapasCulturais\Entities\Opportunity', $key, [
                'label' => sprintf(i::__('PAR - %s'), $label),
                'type' => 'string',
                'private' => false,
            ]);
        }
    }
}
