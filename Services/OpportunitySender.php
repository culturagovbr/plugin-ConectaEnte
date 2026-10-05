<?php

namespace ConectaEnte\Services;

use ConectaEnte\Http\SendResult;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use DateTime;
use MapasCulturais\App;
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

    /** Uma tentativa de envio; em modo dev o desfecho é `simulated`, sem passar pelo transporte. */
    public function send(Opportunity $opportunity): SendOutcome
    {
        if ($this->plugin->isDevMode()) {
            $outcome = SendOutcome::simulated();
            $this->recordOutcome($opportunity, $outcome);
            // a faixa na página não alcança quem drena a fila: sem este registro, o worker simula em silêncio
            App::i()->log->warning("ConectaEnte em modo dev: o edital {$opportunity->id} não foi enviado ao CultBR.");

            return $outcome;
        }

        $federativeEntity = $this->sealedOpportunity->federativeEntityOf($opportunity);

        if (!$federativeEntity) {
            throw new RuntimeException("A oportunidade {$opportunity->id} não é um edital selado por Ente Federado.");
        }

        $payload = $this->plugin->opportunityPayload()->build($opportunity);
        $result = $this->plugin->client()->sendOpportunity($federativeEntity->token, $opportunity->id, $payload);

        $outcome = match (true) {
            $result->unreachable => SendOutcome::unavailable($this->unavailableReason($result)),
            $result->accepted => SendOutcome::success(),
            default => SendOutcome::rejected($result->message ?? i::__('A Plataforma CultBR recusou o envio.')),
        };

        // indisponível não é desfecho final: quem decide reenfileirar é o job, que sabe a tentativa atual
        if ($outcome->isRetryable()) {
            $this->logUnavailable($opportunity, $result);
        } else {
            $this->recordOutcome($opportunity, $outcome);
        }

        return $outcome;
    }

    /** Grava o desfecho final do esgotamento, preservando o motivo da última tentativa. */
    public function recordExhausted(Opportunity $opportunity, SendOutcome $lastAttempt): void
    {
        $reason = trim(($lastAttempt->reason ?? '') . ' ' . i::__('Tentativas esgotadas.'));

        $this->recordOutcome($opportunity, SendOutcome::error($reason));
    }

    // o status é inócuo e identifica a falha; o erro do curl nomeia host e DNS, e o motivo sai na API sem sessão
    private function unavailableReason(SendResult $result): string
    {
        return $result->status > 0
            ? sprintf(i::__('A Plataforma CultBR respondeu com erro HTTP %d.'), $result->status)
            : i::__('A Plataforma CultBR não respondeu.');
    }

    private function logUnavailable(Opportunity $opportunity, SendResult $result): void
    {
        $detail = $result->transportError ?? "HTTP {$result->status}";

        App::i()->log->error("ConectaEnte: oportunidade {$opportunity->id} indisponível no envio ({$detail}).");
    }

    // o motivo é público na API da oportunidade: mensagem de exceção fica no log, não aqui
    public function recordFailure(Opportunity $opportunity): void
    {
        $this->recordOutcome($opportunity, SendOutcome::error(i::__('Falha ao enviar o edital ao CultBR.')));
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
