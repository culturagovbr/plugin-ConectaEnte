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
        $app = App::i();
        $this->requireSaasSuperAdmin();

        $federativeEntities = $app->repo(FederativeEntity::class)->findBy([], ['name' => 'ASC']);
        $sealsByEntity = $app->repo(FederativeEntitySeal::class)->findGroupedByEntity($federativeEntities);

        $this->render('federative-entities', [
            'cards' => array_map(
                fn($federativeEntity) => new FederativeEntityCard($federativeEntity, $sealsByEntity[$federativeEntity->id] ?? []),
                $federativeEntities
            ),
        ]);
    }

    /**
     * Entrega o token de um ente — a listagem só o mostra mascarado — a quem confirmar a própria senha.
     */
    function POST_federativeEntityToken()
    {
        $app = App::i();
        $this->requireSaasSuperAdmin();

        $federativeEntity = $this->requestedFederativeEntity();
        $passwordCheck = new PasswordCheck;

        if (!$passwordCheck->hasPassword($app->user)) {
            $this->errorJson(['password' => [i::__('Sua conta não tem senha local. Defina uma em Conta e Privacidade para revelar tokens.')]], 400);
        }

        if (!$passwordCheck->verify($app->user, (string) ($this->postData['password'] ?? ''))) {
            $this->errorJson(['password' => [i::__('Senha incorreta.')]], 403);
        }

        $this->json(['token' => $federativeEntity->token]);
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

        $this->refuseSealAlreadyInUse($seal);

        $validation = Plugin::instance()->client()->validateToken($token);

        if (!$validation->accepted) {
            $this->errorJson(['token' => [$validation->message]], $validation->unavailable ? 503 : 400);
        }

        if ($existing = $app->repo(FederativeEntity::class)->findOneBy(['document' => $validation->document])) {
            $this->errorJson(['document' => [sprintf(
                i::__('Este CNPJ já está cadastrado no Ente Federado %s.'),
                $existing->name
            )]], 400);
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
            $this->errorJson(['token' => [sprintf(
                i::__('Este token é do CNPJ %s, e o Ente Federado cadastrado é o %s.'),
                $validation->document,
                $federativeEntity->formattedDocument
            )]], 400);
        }

        $federativeEntity->token = $token;
        $federativeEntity->save(true);

        $this->json(true);
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
