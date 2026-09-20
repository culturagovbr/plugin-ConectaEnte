<?php

namespace ConectaEnte\Controllers;

use ConectaEnte\Auth\PasswordCheck;
use ConectaEnte\Dto\FederativeEntityCard;
use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Plugin;
use MapasCulturais\App;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Exceptions\PermissionDenied;
use MapasCulturais\i;

class ConectaEnteController extends \MapasCulturais\Controller
{
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

        if (!$name || !$token || !$seal) {
            $this->errorJson(['form' => [i::__('Informe nome, selo e token.')]], 400);
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
        $this->linkSeal($federativeEntity, $seal);

        $this->json(true);
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

    private function linkSeal(FederativeEntity $federativeEntity, Seal $seal): void
    {
        $link = new FederativeEntitySeal;
        $link->federativeEntity = $federativeEntity;
        $link->seal = $seal;
        $link->save(true);
    }
}
