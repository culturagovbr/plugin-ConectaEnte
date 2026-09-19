<?php

namespace ConectaEnte\Controllers;

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

        if (!App::i()->user->is('saasSuperAdmin')) {
            throw new PermissionDenied(App::i()->user);
        }

        $this->render('federative-entities');
    }
}
