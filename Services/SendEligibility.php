<?php

namespace ConectaEnte\Services;

use MapasCulturais\Entities\Opportunity;
use MapasCulturais\i;

final class SendEligibility
{
    public function __construct(
        private SealedOpportunity $sealedOpportunity,
        private PublicationRequirements $publicationRequirements,
    ) {
    }

    public function isEligible(Opportunity $opportunity): bool
    {
        return $this->ineligibilityReason($opportunity) === null;
    }

    /**
     * Por que a oportunidade não vai ao CultBR agora, ou nulo se for elegível.
     */
    public function ineligibilityReason(Opportunity $opportunity): ?string
    {
        if (!$this->sealedOpportunity->federativeEntityOf($opportunity)) {
            return i::__('Sem selo de Ente Federado cadastrado.');
        }

        if ((int) $opportunity->status !== Opportunity::STATUS_ENABLED) {
            return i::__('A oportunidade não está publicada.');
        }

        $missing = $this->publicationRequirements->missing($opportunity);

        if ($missing) {
            return sprintf(i::__('Faltam %d campo(s) obrigatório(s): %s'), count($missing), implode(', ', array_keys($missing)));
        }

        return null;
    }
}
