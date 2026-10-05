<?php

namespace ConectaEnte\Services;

use MapasCulturais\Entities\Opportunity;
use MapasCulturais\i;

final class SendEligibility
{
    public function __construct(
        private SealedOpportunity $sealedOpportunity,
        private PublicationRequirements $publicationRequirements,
        private FieldLabels $fieldLabels,
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
            return implode(' ', $this->reasonsFor($opportunity, $missing));
        }

        return null;
    }

    /**
     * A razão de cada validação reprovada, com o campo nomeado como o gestor o vê na tela.
     */
    private function reasonsFor(Opportunity $opportunity, array $missing): array
    {
        $labels = $this->fieldLabels->forKeys($opportunity, array_keys($missing));
        $reasons = [];

        foreach ($missing as $key => $messages) {
            $label = $labels[$key] ?? $key;
            $reason = implode(' ', (array) $messages);
            // a maioria das mensagens já nomeia o campo; prefixar todas repetiria o rótulo
            $reasons[] = str_contains($reason, $label) ? $reason : "{$label}: {$reason}";
        }

        return $reasons;
    }
}
