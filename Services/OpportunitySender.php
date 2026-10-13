<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\Log;
use ConectaEnte\Http\HttpExchange;
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
    public function send(Opportunity $opportunity, ?Log $log = null, int $attempt = 1): SendOutcome
    {
        $federativeEntity = $this->sealedOpportunity->federativeEntityOf($opportunity);

        if (!$federativeEntity) {
            $reason = "A oportunidade {$opportunity->id} não é um edital selado por Ente Federado.";
            $this->recordUnsent($log, SendOutcome::error($reason), $attempt, $reason);

            throw new RuntimeException($reason);
        }

        $payload = $this->plugin->opportunityPayload()->build($opportunity);
        $pending = $this->plugin->payloadValidation()->errors($opportunity, $payload);

        // conferir antes do desvio de modo: em dev, pular a conferência esconderia o campo faltando
        if ($pending) {
            App::i()->log->error("ConectaEnte: payload do edital {$opportunity->id} incompleto: " . implode(' ', $pending));
            $outcome = SendOutcome::error($this->pendingReason($pending));
            $this->recordUnsent($log, $outcome, $attempt, $outcome->reason);

            return $this->recorded($opportunity, $outcome);
        }

        if ($this->plugin->isDevMode()) {
            // a faixa na página não alcança quem drena a fila: sem este registro, o worker simula em silêncio
            App::i()->log->warning("ConectaEnte em modo dev: o edital {$opportunity->id} não foi enviado ao CultBR.");
            $outcome = SendOutcome::simulated();
            $this->recordUnsent($log, $outcome, $attempt, null);

            return $this->recorded($opportunity, $outcome);
        }

        // o cliente precisa sobreviver à chamada: é dele que saem as trocas que viram tentativas
        $client = $this->plugin->client();
        $result = $client->sendOpportunity($federativeEntity->token, $payload);

        $outcome = match (true) {
            $result->unreachable => SendOutcome::unavailable($this->unavailableReason($result)),
            $result->accepted => SendOutcome::success(),
            default => SendOutcome::rejected($result->message ?? i::__('A Plataforma CultBR recusou o envio.')),
        };

        $this->keepParEditalId($opportunity, $result, $log);
        $this->recordAttempts($log, $client->exchanges(), $outcome, $attempt);

        // indisponível não é desfecho final: quem decide reenfileirar é o job, que sabe a tentativa atual
        if ($outcome->isRetryable()) {
            $this->logUnavailable($opportunity, $result);
        } else {
            $this->recordOutcome($opportunity, $outcome);
            $this->closeHistory($log, $outcome);
        }

        return $outcome;
    }

    // falha anterior à requisição também deixa tentativa: sem status, sem resposta, com o motivo
    private function recordUnsent(?Log $log, SendOutcome $outcome, int $attempt, ?string $reason): void
    {
        if (!$log) {
            return;
        }

        $exchange = new HttpExchange(
            endpoint: $this->plugin->client()->sendOpportunityUrl(),
            errorMessage: $reason,
        );

        $this->plugin->sendLogger()->recordAttempt($log, $exchange, $outcome->status, $attempt, $this->plugin->sendMaxAttempts());
        $this->closeHistory($log, $outcome);
    }

    private function recordAttempts(?Log $log, array $exchanges, SendOutcome $outcome, int $attempt): void
    {
        if ($log) {
            $this->plugin->sendLogger()->recordAttempts($log, $exchanges, $outcome->status, $attempt, $this->plugin->sendMaxAttempts());
        }
    }

    // o metadado do envio prevalece: envelope que não fecha deixa linha no log, não desfaz o que o CultBR aceitou
    private function closeHistory(?Log $log, SendOutcome $outcome): void
    {
        if ($log && !$this->plugin->sendLogger()->finish($log, $outcome->status)) {
            App::i()->log->warning("ConectaEnte: o histórico do edital {$log->opportunityId} não fechou como {$outcome->status}; vale o desfecho gravado no edital.");
        }
    }

    // o motivo é público na API sem sessão: muitas pendências indicam defeito no build, e aí o log é o lugar
    private function pendingReason(array $pending): string
    {
        $shown = array_slice($pending, 0, self::MAX_PENDING_REASONS);
        $rest = count($pending) - count($shown);

        return trim(implode(' ', $shown) . ($rest > 0 ? ' ' . sprintf(i::__('E outros %d campos.'), $rest) : ''));
    }

    // a resposta aceita traz o id do edital no CultBR: guardá-lo é o que permite correlacionar os dois lados
    private function keepParEditalId(Opportunity $opportunity, SendResult $result, ?Log $log): void
    {
        $id = $result->response['id_par_edital'] ?? null;

        if ($id === null) {
            return;
        }

        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_ID, (string) $id);
        $opportunity->saveMetadata();

        if ($log) {
            $this->plugin->sendLogger()->keepParEdital($log, (int) $id);
        }
    }

    private function recorded(Opportunity $opportunity, SendOutcome $outcome): SendOutcome
    {
        $this->recordOutcome($opportunity, $outcome);

        return $outcome;
    }

    /** Grava o desfecho final do esgotamento, preservando o motivo da última tentativa. */
    public function recordExhausted(Opportunity $opportunity, SendOutcome $lastAttempt, ?Log $log = null): void
    {
        $reason = trim(($lastAttempt->reason ?? '') . ' ' . i::__('Tentativas esgotadas.'));
        $outcome = SendOutcome::error($reason);

        $this->recordOutcome($opportunity, $outcome);
        $this->closeHistory($log, $outcome);
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
    public function recordFailure(Opportunity $opportunity, ?Log $log = null, int $attempt = 1): void
    {
        $outcome = SendOutcome::error(i::__('Falha ao enviar o edital ao CultBR.'));

        $this->recordOutcome($opportunity, $outcome);
        $this->recordUnsent($log, $outcome, $attempt, $outcome->reason);
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
