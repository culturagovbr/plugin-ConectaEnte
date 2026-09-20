<?php

namespace ConectaEnte\Repositories;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Seal;

class FederativeEntitySealRepository extends \MapasCulturais\Repository
{
    /**
     * Vínculo correspondente a um dos selos concedidos da oportunidade, ou nulo.
     */
    function findOneByOpportunity(Opportunity $opportunity): ?FederativeEntitySeal
    {
        $seals = array_map(fn($relation) => $relation->seal, $opportunity->getSealRelations());

        return $seals ? $this->findOneBy(['seal' => $seals]) : null;
    }

    function findOneBySeal(Seal $seal): ?FederativeEntitySeal
    {
        return $this->findOneBy(['seal' => $seal]);
    }

    function findOneByFederativeEntity(FederativeEntity $federativeEntity): ?FederativeEntitySeal
    {
        return $this->findOneBy(['federativeEntity' => $federativeEntity]);
    }

    /**
     * Vínculos dos entes informados, agrupados por ente — uma consulta só, montada em memória.
     *
     * @param FederativeEntity[] $federativeEntities
     * @return array<int, FederativeEntitySeal[]>
     */
    function findGroupedByEntity(array $federativeEntities): array
    {
        if (!$federativeEntities) {
            return [];
        }

        $grouped = [];

        foreach ($this->findBy(['federativeEntity' => $federativeEntities]) as $link) {
            $grouped[$link->federativeEntity->id][] = $link;
        }

        return $grouped;
    }

    /**
     * Ente que impede este selo de ser aplicado à oportunidade, ou nulo se pode.
     */
    function findConflictingEntity(Opportunity $opportunity, Seal $seal): ?FederativeEntity
    {
        if (!$this->findOneBySeal($seal)) {
            return null;
        }

        $sealLink = $this->findOneByOpportunity($opportunity);

        return $sealLink && $sealLink->seal->id !== $seal->id ? $sealLink->federativeEntity : null;
    }
}
