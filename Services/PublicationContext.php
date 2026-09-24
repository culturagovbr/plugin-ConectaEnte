<?php

namespace ConectaEnte\Services;

use MapasCulturais\Entities\Opportunity;

final class PublicationContext
{
    private ?int $opportunityId = null;
    private ?int $requestedStatus = null;

    /**
     * Marca o status ao qual a requisição leva a oportunidade, antes de o status mudar.
     */
    public function enter(Opportunity $opportunity, int $status = Opportunity::STATUS_ENABLED): void
    {
        $this->opportunityId = $opportunity->id;
        $this->requestedStatus = $status;
    }

    /**
     * Encerra a marca, no fim da requisição.
     */
    public function leave(): void
    {
        $this->opportunityId = null;
        $this->requestedStatus = null;
    }

    /**
     * Se a oportunidade sai publicada da requisição: pelo status pedido, ou pelo atual quando nada foi pedido.
     */
    public function endsPublished(Opportunity $opportunity): bool
    {
        $status = $this->isMarked($opportunity, $this->opportunityId) ? $this->requestedStatus : (int) $opportunity->status;

        return $status === Opportunity::STATUS_ENABLED;
    }

    private function isMarked(Opportunity $opportunity, ?int $markedId): bool
    {
        return $markedId !== null && $opportunity->id === $markedId;
    }
}
