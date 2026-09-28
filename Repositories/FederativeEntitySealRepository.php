<?php

namespace ConectaEnte\Repositories;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use Doctrine\ORM\Query\Expr\Join;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\OpportunitySealRelation;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Entities\SealRelation;

class FederativeEntitySealRepository extends \MapasCulturais\Repository
{
    /**
     * Vínculo de um Ente Federado ativo com um dos selos concedidos da oportunidade, ou nulo.
     */
    function findOneByOpportunity(Opportunity $opportunity): ?FederativeEntitySeal
    {
        $link = $this->findAnyByOpportunity($opportunity);

        return $link && (int) $link->federativeEntity->status === FederativeEntity::STATUS_ENABLED ? $link : null;
    }

    // inclui ente na lixeira: o selo dele segue ocupado até restaurar ou destruir
    private function findAnyByOpportunity(Opportunity $opportunity): ?FederativeEntitySeal
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
     * Ids dos selos dos Entes Federados ativos, sem hidratar o vínculo, que carregaria cada selo e seus metadados.
     *
     * @return int[]
     */
    function findSealIdsOfEnabledEntities(): array
    {
        return $this->createQueryBuilder('sealLink')
            ->select('IDENTITY(sealLink.seal)')
            ->join('sealLink.federativeEntity', 'federativeEntity')
            ->where('federativeEntity.status = :status')
            ->setParameter('status', FederativeEntity::STATUS_ENABLED)
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Entes ativos com selo aplicado a alguma oportunidade viva — os únicos cuja árvore do PAR alguém abre.
     *
     * Selo e relação seguem o critério do core em `getSealRelations()`. Oportunidade viva é rascunho
     * ou publicada: fase, arquivada e lixeira não têm quem edite os campos do CultBR.
     *
     * @return FederativeEntity[]
     */
    function findEntitiesSealingLiveOpportunities(): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('federativeEntity')
            ->distinct()
            ->from(FederativeEntity::class, 'federativeEntity')
            ->join('federativeEntity.seals', 'sealLink')
            ->join('sealLink.seal', 'seal')
            ->join(OpportunitySealRelation::class, 'sealRelation', Join::WITH, 'sealRelation.seal = sealLink.seal')
            ->join('sealRelation.owner', 'opportunity')
            ->where('federativeEntity.status = :entityStatus')
            ->andWhere('seal.status IN (:sealStatuses)')
            ->andWhere('sealRelation.status = :relationStatus')
            ->andWhere('opportunity.status >= :opportunityStatus')
            ->setParameter('entityStatus', FederativeEntity::STATUS_ENABLED)
            ->setParameter('sealStatuses', [Seal::STATUS_ENABLED, Seal::STATUS_RELATED])
            ->setParameter('relationStatus', SealRelation::STATUS_ENABLED)
            ->setParameter('opportunityStatus', Opportunity::STATUS_DRAFT)
            ->getQuery()
            ->getResult();
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

        $sealLink = $this->findAnyByOpportunity($opportunity);

        return $sealLink && $sealLink->seal->id !== $seal->id ? $sealLink->federativeEntity : null;
    }
}
