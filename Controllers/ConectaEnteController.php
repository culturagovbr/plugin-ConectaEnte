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
        $app = App::i();
        $this->requireAuthentication();

        if (!$app->user->is('saasSuperAdmin')) {
            throw new PermissionDenied($app->user);
        }

        $this->render('federative-entities');
    }
}
