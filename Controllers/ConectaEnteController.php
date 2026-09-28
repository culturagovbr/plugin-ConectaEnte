<?php

namespace ConectaEnte\Controllers;

use ConectaEnte\Auth\PasswordCheck;
use ConectaEnte\Dto\FederativeEntityCard;
use ConectaEnte\Dto\SealOption;
use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Http\ParInformationResult;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Services\PublicationRequirements;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Exceptions\PermissionDenied;
use MapasCulturais\i;

class ConectaEnteController extends \MapasCulturais\Controller
{
    /** A ordem de leitura da aba, de cima para baixo, card a card. */
    const SCREEN_ORDER = [
        CultBrMetadata::PAR_EXERCISE_ID,
        CultBrMetadata::PAR_GOAL_ID,
        CultBrMetadata::PAR_ACTION_ID,
        CultBrMetadata::PAR_ACTIVITY_ID,
        CultBrMetadata::EXECUTION_TYPE,
        'rules',
        'registrationFrom',
        'registrationTo',
        'registrationProponentTypes',
        CultBrMetadata::LEGAL_ENTITY_TYPES,
        CultBrMetadata::SEGMENTS,
        CultBrMetadata::CULTURAL_STAGES,
        CultBrMetadata::THEMATIC_AGENDAS,
        CultBrMetadata::PRIORITY_TERRITORIES,
        'vacancies',
        'totalResource',
        'registrationRanges',
        CultBrMetadata::QUOTA_RESERVATION,
        CultBrMetadata::FUNDING_SOURCES,
        CultBrMetadata::REGISTRATION_CHANNELS,
        PublicationRequirements::REGISTRATION_CHANNELS_EMAIL,
        CultBrMetadata::AFFIRMATIVE_ACTIONS,
        CultBrMetadata::PUBLISHED_AT,
    ];

    function __construct()
    {
        $this->layout = 'panel';
    }

    function GET_federativeEntities()
    {
        $this->requireSaasSuperAdmin();

        $this->render('federative-entities', [
            'cards' => $this->cards(FederativeEntity::STATUS_ENABLED),
            'trashedCards' => $this->cards(FederativeEntity::STATUS_TRASH),
            'seals' => $this->availableSeals(),
        ]);
    }

    /**
     * Manda o Ente Federado para a lixeira: some da listagem e deixa de integrar, mas segue ocupando CNPJ e selo.
     */
    function DELETE_federativeEntity()
    {
        $this->requireSaasSuperAdmin();
        $this->requirePassword($this->deleteData);

        $this->requestedFederativeEntity()->delete(true);

        $this->json(true);
    }

    /**
     * Recupera o Ente Federado da lixeira.
     */
    function POST_federativeEntityUndelete()
    {
        $this->requireSaasSuperAdmin();
        $this->requirePassword($this->postData);

        $this->requestedFederativeEntity()->undelete(true);

        $this->json(true);
    }

    /**
     * Apaga de vez o Ente Federado, o vínculo com o selo e o token.
     */
    function DELETE_federativeEntityDestroy()
    {
        $this->requireSaasSuperAdmin();
        $this->requirePassword($this->deleteData);

        $this->requestedFederativeEntity()->destroy(true);

        $this->json(true);
    }

    /**
     * Entrega o token de um ente — a listagem só o mostra mascarado — a quem confirmar a própria senha.
     */
    function POST_federativeEntityToken()
    {
        $this->requireSaasSuperAdmin();
        $this->requirePassword($this->postData);

        $this->json(['token' => $this->requestedFederativeEntity()->token]);
    }

    /**
     * Cadastra o ente com o documento que a própria API devolve, e já vincula o primeiro selo.
     */
    function POST_federativeEntities()
    {
        $app = App::i();
        $this->requireSaasSuperAdmin();

        $name = trim((string) ($this->postData['name'] ?? ''));
        $token = trim((string) ($this->postData['token'] ?? ''));
        $seal = $this->requestedSeal();

        $missing = array_filter([
            'name' => $name ? [] : [i::__('Informe o nome do Ente Federado.')],
            'seal' => $seal ? [] : [i::__('Escolha um selo.')],
            'token' => $token ? [] : [i::__('Informe o token.')],
        ]);

        if ($missing) {
            $this->errorJson($missing, 400);
        }

        $this->refuseSealWithValidity($seal);
        $this->refuseSealAlreadyInUse($seal);

        $validation = Plugin::instance()->client()->validateToken($token);

        if (!$validation->accepted) {
            $this->errorJson(['token' => [$validation->message]], $validation->unavailable ? 503 : 400);
        }

        if ($app->repo(FederativeEntity::class)->findOneBy(['document' => $validation->document])) {
            $this->errorJson(['document' => [i::__('Este CNPJ já está cadastrado.')]], 400);
        }

        $federativeEntity = new FederativeEntity;
        $federativeEntity->name = $name;
        $federativeEntity->document = $validation->document;
        $federativeEntity->token = $token;
        $federativeEntity->save(true);

        $this->linkSeal($federativeEntity, $seal);

        $this->json(['id' => $federativeEntity->id]);
    }

    /**
     * Cadastra o selo de um ente que ainda não tem.
     */
    function POST_federativeEntitySeal()
    {
        $this->requireSaasSuperAdmin();

        $federativeEntity = $this->requestedFederativeEntity();
        $seal = $this->requestedSeal();

        if (!$seal) {
            $this->errorJson(['seal' => [i::__('Escolha um selo.')]], 400);
        }

        $this->refuseEntityThatAlreadyHasSeal($federativeEntity);
        $this->refuseSealWithValidity($seal);
        $this->refuseSealAlreadyInUse($seal);

        $this->json(FederativeEntityCard::seal($this->linkSeal($federativeEntity, $seal)));
    }

    /**
     * Remove o selo do ente, que volta a não ser reconhecido em nenhuma oportunidade.
     */
    function DELETE_federativeEntitySeal()
    {
        $this->requireSaasSuperAdmin();

        $link = App::i()->repo(FederativeEntitySeal::class)->findOneByFederativeEntity($this->requestedFederativeEntity());

        if (!$link) {
            App::i()->pass();
        }

        $link->delete(true);

        $this->json(true);
    }

    /**
     * Troca o token do ente. Campo vazio mantém o que está gravado.
     */
    function PATCH_federativeEntity()
    {
        $this->requireSaasSuperAdmin();

        $federativeEntity = $this->requestedFederativeEntity();
        $token = trim((string) ($this->patchData['token'] ?? ''));

        if (!$token) {
            $this->json(true);
        }

        $validation = Plugin::instance()->client()->validateToken($token);

        if (!$validation->accepted) {
            $this->errorJson(['token' => [$validation->message]], $validation->unavailable ? 503 : 400);
        }

        if ($validation->document !== $federativeEntity->document) {
            $this->errorJson(['token' => [i::__('Este token não pertence a este Ente Federado.')]], 400);
        }

        $federativeEntity->token = $token;
        $federativeEntity->save(true);

        $this->json(true);
    }

    /**
     * O que falta para publicar a oportunidade selada, com o rótulo de cada campo.
     */
    function GET_opportunityRequirements()
    {
        $this->requireAuthentication();

        $opportunity = $this->requestedOpportunity();
        $opportunity->checkPermission('modify');

        $isSealed = Plugin::instance()->sealedOpportunity()->isSealed($opportunity);
        $missing = $isSealed ? $this->inScreenOrder($this->publicationErrors($opportunity)) : [];

        $this->json([
            'sealed' => $isSealed,
            'missing' => $this->withoutPlainRequiredMessages($missing),
            'labels' => $this->fieldLabels($opportunity, array_keys($missing)),
            'anchors' => $this->fieldAnchors(array_keys($missing)),
            'groups' => $this->fieldGroups(array_keys($missing)),
        ]);
    }

    /**
     * A árvore do PAR do ente ligado à oportunidade pelo selo, para a cascata da aba.
     */
    function GET_parInformation()
    {
        $this->requireAuthentication();

        $opportunity = $this->requestedOpportunity();
        $opportunity->checkPermission('modify');

        $result = Plugin::instance()->parInformationService()->getForOpportunity($opportunity);

        if (!$result) {
            $this->json(['available' => true, 'exercicios' => []]);
        }

        // sem árvore a tela precisa do motivo: token recusado não se resolve esperando, falha do CultBR sim
        if (!$result->tree) {
            $this->json(['available' => false, 'reason' => $this->parUnavailableReason($result), 'exercicios' => []]);
        }

        $this->json(['available' => true, 'simulated' => Plugin::instance()->isDevMode()] + $result->tree->jsonSerialize());
    }

    private function parUnavailableReason(ParInformationResult $result): string
    {
        return match (true) {
            $result->notFound => 'notFound',
            $result->unreachable => 'unreachable',
            default => 'rejected',
        };
    }

    /** @return SealOption[] selos habilitados que nenhum Ente Federado usa, nem na lixeira */
    private function availableSeals(): array
    {
        $app = App::i();
        $seals = $app->repo(Seal::class)->findBy(['status' => Seal::STATUS_ENABLED], ['name' => 'ASC']);
        $linkedSeals = array_map(fn($link) => $link->seal, $app->repo(FederativeEntitySeal::class)->findAll());
        // comparação estrita de instância: o Doctrine garante uma por id, e ler ->id inicializaria cada proxy
        $freeSeals = array_filter($seals, fn($seal) => !in_array($seal, $linkedSeals, true));

        return array_map(fn($seal) => new SealOption($seal), array_values($freeSeals));
    }

    /** @return FederativeEntityCard[] */
    private function cards(int $status): array
    {
        $app = App::i();
        $federativeEntities = $app->repo(FederativeEntity::class)->findBy(['status' => $status], ['name' => 'ASC']);
        $sealsByEntity = $app->repo(FederativeEntitySeal::class)->findGroupedByEntity($federativeEntities);

        return array_map(
            fn($federativeEntity) => new FederativeEntityCard($federativeEntity, $sealsByEntity[$federativeEntity->id] ?? []),
            $federativeEntities
        );
    }

    /**
     * Toda ação sensível é assinada com a senha do próprio administrador; a senha vale por uma janela.
     */
    private function requirePassword(array $data): void
    {
        $app = App::i();
        $window = Plugin::instance()->passwordWindow();

        if (!array_key_exists('password', $data)) {
            if ($window->isOpen()) {
                return;
            }

            $this->errorJson(['password' => [i::__('Confirme sua senha.')]], 401);
        }

        $passwordCheck = new PasswordCheck;

        if (!$passwordCheck->hasPassword($app->user)) {
            $this->errorJson(['password' => [i::__('Sua conta não tem senha local. Defina uma em Conta e Privacidade.')]], 400);
        }

        if (!$passwordCheck->verify($app->user, (string) $data['password'])) {
            $this->errorJson(['password' => [i::__('Senha incorreta.')]], 403);
        }

        $window->open();
    }

    private function requireSaasSuperAdmin(): void
    {
        $app = App::i();
        $this->requireAuthentication();

        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user);
        }
    }

    private function requestedFederativeEntity(): FederativeEntity
    {
        $federativeEntity = App::i()->repo(FederativeEntity::class)->find($this->urlData['id'] ?? 0);

        if (!$federativeEntity) {
            App::i()->pass();
        }

        return $federativeEntity;
    }

    private function requestedOpportunity(): Opportunity
    {
        $opportunity = App::i()->repo(Opportunity::class)->find($this->urlData['id'] ?? 0);

        if (!$opportunity) {
            App::i()->pass();
        }

        return $opportunity;
    }

    // os mesmos erros que a publicação devolveria agora, sem publicar
    private function publicationErrors(Opportunity $opportunity): array
    {
        $context = Plugin::instance()->publicationContext();
        $context->simulate($opportunity);

        try {
            return $opportunity->validationErrors;
        } finally {
            $context->leave();
        }
    }

    private function fieldLabels(Opportunity $opportunity, array $keys): array
    {
        $description = $opportunity::getPropertiesMetadata();
        $labels = [];

        foreach ($keys as $key) {
            $labels[$key] = $this->fieldLabel($description, $key);
        }

        return $labels;
    }

    /** A ordem em que a aba exibe os campos; chave de fora vai para o fim. */
    private function inScreenOrder(array $missing): array
    {
        $order = array_flip(self::SCREEN_ORDER);
        $last = count($order);

        uksort($missing, fn($a, $b) => ($order[$a] ?? $last) <=> ($order[$b] ?? $last));

        return $missing;
    }

    /**
     * As mensagens que só repetem o rótulo saem: a tela mostra o nome do campo e basta.
     *
     * Reconhece a fórmula em pt-br, que é a língua das mensagens de publicação; a que traz
     * regra além da obrigatoriedade fica.
     */
    private function withoutPlainRequiredMessages(array $missing): array
    {
        foreach ($missing as $key => $messages) {
            $missing[$key] = array_values(array_filter(
                $messages,
                fn($message) => !preg_match('/\bé obrigatóri[ao]\.?$/u', trim((string) $message)),
            ));
        }

        return $missing;
    }

    /**
     * A origem de cada chave pendente: campo do plugin ou campo do core que ele exige.
     *
     * @return array<string, string>
     */
    private function fieldGroups(array $keys): array
    {
        $groups = [];

        foreach ($keys as $key) {
            $isPluginField = str_starts_with($key, CultBrMetadata::PREFIX) || in_array($key, CultBrMetadata::PAR_KEYS, true);
            $groups[$key] = $isPluginField ? 'plugin' : 'core';
        }

        return $groups;
    }

    /**
     * O `data-field` que a tela usa para rolar até o campo, por chave pendente.
     *
     * @return array<string, string>
     */
    private function fieldAnchors(array $keys): array
    {
        $anchors = [];

        foreach ($keys as $key) {
            $anchors[$key] = $this->fieldAnchor($key);
        }

        return $anchors;
    }

    /** A chave é o próprio `data-field`, salvo quando a pendência não tem campo só dela. */
    private function fieldAnchor(string $key): string
    {
        return match ($key) {
            PublicationRequirements::REGISTRATION_CHANNELS_EMAIL => CultBrMetadata::REGISTRATION_CHANNELS,
            default => $key,
        };
    }

    // chaves sem rótulo na descrição da entidade levam o texto da tela do core
    private function fieldLabel(array $description, string $key): string
    {
        return match ($key) {
            'rules' => i::__('Regulamento'),
            'term-area' => i::__('Área de Interesse'),
            'registrationRanges' => i::__('Faixas/linhas'),
            PublicationRequirements::REGISTRATION_CHANNELS_EMAIL => $description[CultBrMetadata::REGISTRATION_CHANNELS]['label'],
            default => ($description[$key]['label'] ?? '') ?: $key,
        };
    }

    private function requestedSeal(): ?Seal
    {
        $sealId = $this->data['sealId'] ?? null;

        return $sealId ? App::i()->repo(Seal::class)->find($sealId) : null;
    }

    // a relação de selo com validade expira, e a integração expiraria junto, sem aviso
    private function refuseSealWithValidity(Seal $seal): void
    {
        if ($seal->validPeriod > 0) {
            $this->errorJson(['seal' => [sprintf(
                i::__('Este selo tem validade de %d meses. Edite o selo e remova a validade antes de cadastrá-lo no Ente Federado.'),
                $seal->validPeriod
            )]], 400);
        }
    }

    private function refuseSealAlreadyInUse(Seal $seal): void
    {
        $link = App::i()->repo(FederativeEntitySeal::class)->findOneBySeal($seal);

        if ($link) {
            $this->errorJson(['seal' => [sprintf(
                i::__('Este selo já é do Ente Federado %s.'),
                $link->federativeEntity->name
            )]], 400);
        }
    }

    private function refuseEntityThatAlreadyHasSeal(FederativeEntity $federativeEntity): void
    {
        $link = App::i()->repo(FederativeEntitySeal::class)->findOneByFederativeEntity($federativeEntity);

        if ($link) {
            $this->errorJson(['seal' => [sprintf(
                i::__('Cada Ente Federado tem um selo só, e %s já está com o selo %s.'),
                $federativeEntity->name,
                $link->seal->name
            )]], 400);
        }
    }

    private function linkSeal(FederativeEntity $federativeEntity, Seal $seal): FederativeEntitySeal
    {
        $link = new FederativeEntitySeal;
        $link->federativeEntity = $federativeEntity;
        $link->seal = $seal;
        $link->save(true);

        return $link;
    }
}
