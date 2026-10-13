<?php

namespace ConectaEnte\Services;

use ConectaEnte\Metadata\CultBrMetadata;
use MapasCulturais\Entities\Opportunity;

/**
 * O que o indicador da oportunidade mostra enquanto não há desfecho de envio gravado.
 */
final class SendStatusView
{
    const AWAITING = 'awaiting';
    const INELIGIBLE = 'ineligible';

    public function __construct(
        private SealedOpportunity $sealedOpportunity,
        private SendEligibility $sendEligibility,
    ) {
    }

    public function of(?Opportunity $opportunity): array
    {
        return ['pendingSendState' => $this->pendingSendState($opportunity)];
    }

    private function pendingSendState(?Opportunity $opportunity): ?string
    {
        if (!$opportunity || !$this->sealedOpportunity->federativeEntityOf($opportunity)) {
            return null;
        }

        // com desfecho gravado a tela mostra o desfecho: conferir o gate custaria as onze validações sem mudar nada
        if ($opportunity->getMetadata(CultBrMetadata::SEND_STATUS)) {
            return null;
        }

        return $this->sendEligibility->isEligible($opportunity) ? self::AWAITING : self::INELIGIBLE;
    }
}
