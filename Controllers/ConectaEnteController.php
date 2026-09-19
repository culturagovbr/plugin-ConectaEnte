<?php

namespace ConectaEnte\Controllers;

class ConectaEnteController extends \MapasCulturais\Controller
{
    function __construct()
    {
        $this->layout = 'panel';
    }

    function GET_federativeEntities()
    {
        $this->requireAuthentication();
        $this->render('federative-entities');
    }
}
