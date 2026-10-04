<?php

namespace ConectaEnte;

use ConectaEnte\Auth\PasswordWindow;
use ConectaEnte\Controllers\ConectaEnteController;
use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Http\Client;
use ConectaEnte\Http\Transport\CurlTransport;
use ConectaEnte\Http\Transport\FixtureTransport;
use ConectaEnte\Http\Transport\TransportInterface;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Payload\OpportunityPayload;
use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Jobs\ParInformationFetchJob;
use ConectaEnte\Jobs\ParInformationSyncJob;
use ConectaEnte\Jobs\SendOpportunityJob;
use ConectaEnte\Services\FundingSourceName;
use ConectaEnte\Services\OpportunitySender;
use ConectaEnte\Services\ParInformationService;
use ConectaEnte\Services\PublicationContext;
use ConectaEnte\Services\PublicationRequirements;
use ConectaEnte\Services\PublicationStamp;
use ConectaEnte\Services\CoreFieldsDescription;
use ConectaEnte\Services\SealedOpportunity;
use ConectaEnte\Services\SendEligibility;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Exceptions\BadRequest;
use MapasCulturais\i;
use MapasCulturais\App;

class Plugin extends \MapasCulturais\Plugin
{
    const DEFAULT_HOST = 'https://ente.conecta.hmg.cultbr.cultura.gov.br';
    const DEFAULT_PASSWORD_WINDOW = 120;
    const DEFAULT_PAR_SYNC_INTERVAL_MINUTES = 30;
    const DEFAULT_PAR_CACHE_TTL_MINUTES = 10;
    const DEFAULT_SEND_MAX_ATTEMPTS = 3;
    const DEFAULT_SEND_RETRY_DELAY_SECONDS = 30;

    /** Em `dev` nenhuma rota do CultBR é chamada: a resposta vem de `fixtures/<rota>.json`. */
    const MODE_LIVE = 'live';
    const MODE_DEV = 'dev';

    function __construct(array $config = [])
    {
        $config += [
            'host' => env('CONECTAENTE_HOST', self::DEFAULT_HOST),
            'passwordWindow' => (int) env('CONECTAENTE_PASSWORD_WINDOW', self::DEFAULT_PASSWORD_WINDOW),
            'parSyncIntervalMinutes' => (int) env('CONECTAENTE_PAR_SYNC_INTERVAL_MINUTES', self::DEFAULT_PAR_SYNC_INTERVAL_MINUTES),
            'parCacheTtlMinutes' => (int) env('CONECTAENTE_PAR_CACHE_TTL_MINUTES', self::DEFAULT_PAR_CACHE_TTL_MINUTES),
            'sendMaxAttempts' => (int) env('CONECTAENTE_SEND_MAX_ATTEMPTS', self::DEFAULT_SEND_MAX_ATTEMPTS),
            'sendRetryDelaySeconds' => (int) env('CONECTAENTE_SEND_RETRY_DELAY_SECONDS', self::DEFAULT_SEND_RETRY_DELAY_SECONDS),
            // enquanto o CultBR não estabiliza, a instalação nasce em dev e declara CONECTAENTE_MODE=live para valer
            'mode' => env('CONECTAENTE_MODE', self::MODE_DEV),
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
        $transport = $this->transport ?? $this->defaultTransport();

        // só a fixture precisa servir a qualquer ente; com transporte real o cnpj continua filtrando
        return new Client($this->_config['host'], $transport, $transport instanceof FixtureTransport);
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

    function opportunityPayload(): OpportunityPayload
    {
        return new OpportunityPayload($this->sealedOpportunity());
    }

    function coreFieldsDescription(): CoreFieldsDescription
    {
        return new CoreFieldsDescription($this->sealedOpportunity());
    }

    function fundingSourceName(): FundingSourceName
    {
        return new FundingSourceName();
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
        return new PublicationRequirements($this->publicationStamp(), $this->parInformationService());
    }

    function sendEligibility(): SendEligibility
    {
        return new SendEligibility($this->sealedOpportunity(), $this->publicationRequirements());
    }

    function opportunitySender(): OpportunitySender
    {
        return new OpportunitySender($this, $this->sealedOpportunity());
    }

    function sendMaxAttempts(): int
    {
        return (int) $this->_config['sendMaxAttempts'];
    }

    function sendRetryDelaySeconds(): int
    {
        return (int) $this->_config['sendRetryDelaySeconds'];
    }

    /**
     * Enfileira o envio da oportunidade, se ela for raiz e estiver elegível agora.
     */
    function scheduleSend(Opportunity $opportunity, int $attempt = 1, string $start = 'now'): void
    {
        // sem selo de ente, nem vale perguntar o resto: é o caso comum de toda oportunidade da
        // instalação, e logar isso a cada save encheria o log sem dizer nada de novo
        if ($opportunity->parent || !$this->sealedOpportunity()->federativeEntityOf($opportunity)) {
            return;
        }

        if (!$this->sendEligibility()->isEligible($opportunity)) {
            App::i()->log->info("ConectaEnte: oportunidade {$opportunity->id} não elegível para envio: {$this->sendEligibility()->ineligibilityReason($opportunity)}");

            return;
        }

        App::i()->enqueueJob(SendOpportunityJob::SLUG, SendOpportunityJob::dataFor($opportunity, $attempt), $start, '', 1, true);
    }

    /**
     * Agenda a próxima sincronização do PAR, uma iteração por vez.
     *
     * O job se apaga ao executar e o boot seguinte o recria; preso em PROCESSING,
     * a limpeza de 5 minutos do core só o recolhe com iterations = 1.
     */
    function scheduleParSync(): void
    {
        App::i()->enqueueJob(ParInformationSyncJob::SLUG, [], "+{$this->_config['parSyncIntervalMinutes']} minutes", '', 1);
    }

    /** Enfileira a busca da árvore deste ente para agora, se ele estiver ativo. */
    function scheduleParFetch(FederativeEntity $federativeEntity): void
    {
        if ((int) $federativeEntity->status !== FederativeEntity::STATUS_ENABLED) {
            return;
        }

        $app = App::i();
        $data = ParInformationFetchJob::dataFor($federativeEntity);
        $jobType = $app->getRegisteredJobType(ParInformationFetchJob::SLUG);
        $scheduled = $app->repo(Job::class)->findOneBy(['id' => $jobType->generateId($data, 'now', '', 1)]);

        // substituir o job no meio de uma execução derrubaria o worker que o carrega
        if (!$scheduled || $scheduled->status === Job::STATUS_WAITING) {
            $app->enqueueJob(ParInformationFetchJob::SLUG, $data, 'now', '', 1, true);
        }
    }

    /** Modo alternativo, para os testes exercitarem as duas faces sem trocar a configuração da instalação. */
    public ?string $mode = null;

    function isDevMode(): bool
    {
        return ($this->mode ?? $this->_config['mode']) === self::MODE_DEV;
    }

    function fixturesPath(): string
    {
        return __DIR__ . '/fixtures';
    }

    private function defaultTransport(): TransportInterface
    {
        return $this->isDevMode() ? new FixtureTransport($this->fixturesPath()) : new CurlTransport();
    }

    /** Serviço alternativo, para os testes controlarem cache/transporte da árvore do PAR. */
    public ?ParInformationService $parInformationService = null;

    function parInformationService(): ParInformationService
    {
        // TTL curto: o CultBR muda o PAR em intervalo imprevisível, e árvore vencida faz o gestor escolher o que não existe
        return $this->parInformationService ?? new ParInformationService($this->_config['parCacheTtlMinutes'] * 60, $this->client());
    }

    public function _init(){
        $app = App::i();

        $app->registerJobType(new ParInformationSyncJob(ParInformationSyncJob::SLUG));
        $app->registerJobType(new ParInformationFetchJob(ParInformationFetchJob::SLUG));
        $app->registerJobType(new SendOpportunityJob(SendOpportunityJob::SLUG));

        try {
            $this->scheduleParSync();
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            // tabela job ainda não criada (migração em andamento)
        }

        // token novo ou trocado não espera o próximo ciclo
        $app->hook('entity(ConectaEnte.Entities.FederativeEntity).save:after', function () {
            Plugin::instance()->scheduleParFetch($this);
        });

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

        $app->hook('entity(Opportunity).propertiesMetadata', function (&$propertiesMetadata) {
            Plugin::instance()->coreFieldsDescription()->complete($propertiesMetadata);
        });

        // metadado alterado aqui ainda entra no saveMetadata do mesmo save
        $app->hook('entity(Opportunity).save:before', function () {
            Plugin::instance()->publicationStamp()->stamp($this);
            Plugin::instance()->fundingSourceName()->sanitizeOpportunity($this);
        });

        // o CultEditais saneia aqui, e só aqui; o save:before acima cobre o resto
        $app->hook('PATCH(opportunity.single):data', function (&$data) {
            $key = CultBrMetadata::FUNDING_SOURCES;

            if (isset($data[$key])) {
                $data[$key] = Plugin::instance()->fundingSourceName()->sanitizeBlock($data[$key]);
            }
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

        // abaixo do cabeçalho, em qualquer página: ninguém deve confundir dado de exemplo com dado do CultBR
        $app->hook('view.partial(main-header):after', function ($template, &$html) {
            if (Plugin::instance()->isDevMode()) {
                $html .= $this->partialRender('conectaente/dev-mode-banner', [], true);
            }
        });

        // por último: o tema registra seus hooks depois do plugin, e os erros dele também ficam no PATCH
        $app->hook('entity(Opportunity).validationErrors', function (&$errors) {
            Plugin::instance()->requirePublicationFields($this, $errors);
        }, 1000);

        // publicar dispara o envio, e editar uma já publicada reenvia — insert nunca vê publicada,
        // porque toda oportunidade nasce rascunho
        $app->hook('entity(Opportunity).update:finish', function () {
            Plugin::instance()->scheduleSend($this);
        });

        // selar não passa pelo save da oportunidade, e tem que ser pós-flush: a elegibilidade lê o selo no banco
        $app->hook('entity(OpportunitySealRelation).save:finish', function () {
            Plugin::instance()->scheduleSend($this->owner);
        });
    }

    private function requiresPublicationFields(Opportunity $opportunity): bool
    {
        // cobrados ao publicar, não a cada salvamento: no PATCH o status em memória já é o pedido, e quem sabe é o banco
        return $this->publicationContext()->isSimulating($opportunity)
            || !$this->publicationStamp()->wasPublished($opportunity);
    }

    /**
     * Soma aos erros da oportunidade selada o que falta para ela sair publicada da requisição.
     */
    function requirePublicationFields(Opportunity $opportunity, array &$errors): void
    {
        if (!$this->publicationContext()->endsPublished($opportunity) || !$this->sealedOpportunity()->isSealed($opportunity)) {
            return;
        }

        if ($this->requiresPublicationFields($opportunity)) {
            $errors = array_merge_recursive($errors, $this->publicationRequirements()->missing($opportunity));
        }

        // o PATCH continua guardando o que outros hooks acharam, mesmo quando o plugin não cobra nada
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
