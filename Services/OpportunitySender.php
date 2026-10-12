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

class OpportunitySender
{
    const MAX_PENDING_REASONS = 3;

    public function __construct(
        private Plugin $plugin,
        private SealedOpportunity $sealedOpportunity,
    ) {
    }

    /** Uma tentativa de envio; o payload é montado e conferido antes de a simulação ser decidida. */
    public function send(Opportunity $opportunity): SendOutcome
    {
        $federativeEntity = $this->sealedOpportunity->federativeEntityOf($opportunity);

        if (!$federativeEntity) {
            throw new RuntimeException("A oportunidade {$opportunity->id} não é um edital selado por Ente Federado.");
        }

        $payload = $this->plugin->opportunityPayload()->build($opportunity);
        $pending = $this->plugin->payloadValidation()->errors($opportunity, $payload);

        // conferir antes do desvio de modo: em dev, pular a conferência esconderia o campo faltando
        if ($pending) {
            App::i()->log->error("ConectaEnte: payload do edital {$opportunity->id} incompleto: " . implode(' ', $pending));

            return $this->recorded($opportunity, SendOutcome::error($this->pendingReason($pending)));
        }

        if ($this->plugin->isDevMode()) {
            // a faixa na página não alcança quem drena a fila: sem este registro, o worker simula em silêncio
            App::i()->log->warning("ConectaEnte em modo dev: o edital {$opportunity->id} não foi enviado ao CultBR.");

            return $this->recorded($opportunity, SendOutcome::simulated());
        }

        $result = $this->plugin->client()->sendOpportunity($federativeEntity->token, $payload);

        $outcome = match (true) {
            $result->unreachable => SendOutcome::unavailable($this->unavailableReason($result)),
            $result->accepted => SendOutcome::success(),
            default => SendOutcome::rejected($result->message ?? i::__('A Plataforma CultBR recusou o envio.')),
        };

        $this->keepParEditalId($opportunity, $result);

        // indisponível não é desfecho final: quem decide reenfileirar é o job, que sabe a tentativa atual
        if ($outcome->isRetryable()) {
            $this->logUnavailable($opportunity, $result);
        } else {
            $this->recordOutcome($opportunity, $outcome);
        }

        return $outcome;
    }

    // o motivo é público na API sem sessão: muitas pendências indicam defeito no build, e aí o log é o lugar
    private function pendingReason(array $pending): string
    {
        $shown = array_slice($pending, 0, self::MAX_PENDING_REASONS);
        $rest = count($pending) - count($shown);

        return trim(implode(' ', $shown) . ($rest > 0 ? ' ' . sprintf(i::__('E outros %d campos.'), $rest) : ''));
    }

    // a resposta aceita traz o id do edital no CultBR: guardá-lo é o que permite correlacionar os dois lados
    private function keepParEditalId(Opportunity $opportunity, SendResult $result): void
    {
        $id = $result->response['id_par_edital'] ?? null;

        if ($id !== null) {
            $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_ID, (string) $id);
            $opportunity->saveMetadata();
        }
    }

    private function recorded(Opportunity $opportunity, SendOutcome $outcome): SendOutcome
    {
        $this->recordOutcome($opportunity, $outcome);

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
