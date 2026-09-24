<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Repositories\FederativeEntitySealRepository;
use MapasCulturais\Entities\Opportunity;

final class SealedOpportunity
{
    public function __construct(private FederativeEntitySealRepository $sealLinks)
    {
    }

    /**
     * Se a oportunidade é raiz e carrega o selo concedido de um Ente Federado ativo.
     */
    function isSealed(Opportunity $opportunity): bool
    {
        return $this->federativeEntityOf($opportunity) !== null;
    }

    /**
     * Ente Federado dono do selo da oportunidade raiz, ou nulo.
     */
    function federativeEntityOf(Opportunity $opportunity): ?FederativeEntity
    {
        if ($opportunity->parent) {
            return null;
        }

        return $this->sealLinks->findOneByOpportunity($opportunity)?->federativeEntity;
    }
}
