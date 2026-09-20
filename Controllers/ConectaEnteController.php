<?php

namespace ConectaEnte\Controllers;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use MapasCulturais\App;
use MapasCulturais\Exceptions\PermissionDenied;

class ConectaEnteController extends \MapasCulturais\Controller
{
    function __construct()
    {
        $this->layout = 'panel';
    }

    function GET_federativeEntities()
    {
        $app = App::i();
        $this->requireAuthentication();

        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user);
        }

        $federativeEntities = $app->repo(FederativeEntity::class)->findBy([], ['name' => 'ASC']);

        $this->render('federative-entities', [
            'federativeEntities' => $federativeEntities,
            'sealsByEntity' => $app->repo(FederativeEntitySeal::class)->findGroupedByEntity($federativeEntities),
        ]);
    }
}
