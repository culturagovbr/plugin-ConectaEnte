<?php

namespace ConectaEnte\Services;

use MapasCulturais\Entities\Opportunity;

final class PublicationContext
{
    private ?int $opportunityId = null;
    private ?int $requestedStatus = null;
    private ?int $patchedOpportunityId = null;

    /**
     * Marca o status ao qual a requisição leva a oportunidade, antes de o status mudar.
     */
    public function enter(Opportunity $opportunity, int $status = Opportunity::STATUS_ENABLED): void
    {
        $this->opportunityId = $opportunity->id;
        $this->requestedStatus = $status;
    }

    /**
     * Marca a oportunidade alterada pelo PATCH da requisição.
     */
    public function patching(Opportunity $opportunity): void
    {
        $this->patchedOpportunityId = $opportunity->id;
    }

    /**
     * Encerra as marcas, no fim da requisição.
     */
    public function leave(): void
    {
        $this->opportunityId = null;
        $this->requestedStatus = null;
        $this->patchedOpportunityId = null;
    }

    /**
     * Se a oportunidade sai publicada da requisição: pelo status pedido, ou pelo atual quando nada foi pedido.
     */
    public function endsPublished(Opportunity $opportunity): bool
    {
        $status = $this->isMarked($opportunity, $this->opportunityId) ? $this->requestedStatus : (int) $opportunity->status;

        return $status === Opportunity::STATUS_ENABLED;
    }

    /**
     * Se a oportunidade é a alterada pelo PATCH da requisição.
     */
    public function isPatching(Opportunity $opportunity): bool
    {
        return $this->isMarked($opportunity, $this->patchedOpportunityId);
    }

    private function isMarked(Opportunity $opportunity, ?int $markedId): bool
    {
        return $markedId !== null && $opportunity->id === $markedId;
    }
}
