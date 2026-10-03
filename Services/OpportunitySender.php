<?php

namespace ConectaEnte\Services;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use DateTime;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\i;
use RuntimeException;

final class OpportunitySender
{
    public function __construct(
        private Plugin $plugin,
        private SealedOpportunity $sealedOpportunity,
    ) {
    }

    /**
     * Uma tentativa de envio. Em modo dev não chama a API — a fixture não representa o
     * edital enviado, então o desfecho é `simulated` direto, sem passar pelo transporte.
     */
    public function send(Opportunity $opportunity): SendOutcome
    {
        if ($this->plugin->isDevMode()) {
            $outcome = SendOutcome::simulated();
            $this->recordOutcome($opportunity, $outcome);

            return $outcome;
        }

        $federativeEntity = $this->sealedOpportunity->federativeEntityOf($opportunity);

        if (!$federativeEntity) {
            throw new RuntimeException("A oportunidade {$opportunity->id} não é um edital selado por Ente Federado.");
        }

        $payload = $this->plugin->opportunityPayload()->build($opportunity);
        $result = $this->plugin->client()->sendOpportunity($federativeEntity->token, $opportunity->id, $payload);

        $outcome = match (true) {
            $result->unreachable => SendOutcome::unavailable(),
            $result->accepted => SendOutcome::success(),
            default => SendOutcome::rejected($result->message ?? i::__('A Plataforma CultBR recusou o envio.')),
        };

        // indisponível não é desfecho final: quem decide reenfileirar é o job, que sabe a tentativa atual
        if (!$outcome->isRetryable()) {
            $this->recordOutcome($opportunity, $outcome);
        }

        return $outcome;
    }

    public function recordExhausted(Opportunity $opportunity): void
    {
        $this->recordOutcome($opportunity, SendOutcome::error(i::__('A Plataforma CultBR não respondeu após todas as tentativas.')));
    }

    // via saveMetadata(), nunca save(): salvar a oportunidade de novo reacionaria o próprio gatilho de envio
    private function recordOutcome(Opportunity $opportunity, SendOutcome $outcome): void
    {
        $opportunity->setMetadata(CultBrMetadata::SEND_STATUS, $outcome->status);
        $opportunity->setMetadata(CultBrMetadata::SEND_REASON, $outcome->reason);
        $opportunity->setMetadata(CultBrMetadata::SEND_AT, (new DateTime())->format('Y-m-d H:i:s'));
        $opportunity->saveMetadata();
    }
}
