<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Repositories\FederativeEntityRepository;

/**
 * Responde se uma oportunidade é "selada" (tem o selo de um ente federado cadastrado) e de qual ente.
 * É a condição que liga todo o resto do plugin: sem selo de ente, a oportunidade segue como no core.
 */
class SealedOpportunity
{
    private $repository;

    public function __construct(FederativeEntityRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param object $opportunity qualquer objeto com `getSealRelations()`
     *
     * @return bool
     */
    public function isSealed($opportunity)
    {
        return $this->entityFor($opportunity) !== null;
    }

    /**
     * O ente do primeiro selo aplicado que seja de ente; a regra da #44 garante que há no máximo um.
     * Só as relações habilitadas contam: a pendente de aprovação ainda não é selo.
     *
     * @param object $opportunity qualquer objeto com `getSealRelations()`
     *
     * @return FederativeEntity|null
     */
    public function entityFor($opportunity)
    {
        foreach ($opportunity->getSealRelations() as $relation) {
            $entity = $this->repository->findBySealId($relation->seal->id);

            if ($entity !== null) {
                return $entity;
            }
        }

        return null;
    }
}
