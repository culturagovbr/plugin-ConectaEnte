<?php

namespace ConectaEnte\Controllers;

use AldirBlanc\Services\UserAccessService;
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
        $this->requireAuthentication();

        if (!UserAccessService::isSaasSuperAdmin()) {
            throw new PermissionDenied(App::i()->user);
        }

        $this->render('federative-entities');
    }
}
