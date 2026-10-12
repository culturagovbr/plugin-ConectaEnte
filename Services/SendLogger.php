<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\Log;
use ConectaEnte\Entities\LogAttempt;
use ConectaEnte\Http\HttpExchange;
use DateTime;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\User;
use Throwable;

/**
 * O ciclo do histórico de um envio: abre o envelope, grava cada tentativa e fecha o desfecho.
 *
 * Nenhum método propaga exceção: o histórico registra o envio, não o comanda.
 */
class SendLogger
{
    const MAX_STORED_BYTES = 65536;
    const MAX_REQUEST_ID_BYTES = 255;

    private const FINAL_OUTCOMES = [
        SendOutcome::SUCCESS,
        SendOutcome::SIMULATED,
        SendOutcome::REJECTED,
        SendOutcome::ERROR,
    ];

    /** Abre o envelope do envio, ou retoma pelo uuid o que a retentativa trouxe no job. */
    public function startOrResume(Opportunity $opportunity, ?string $uuid = null): ?Log
    {
        $app = App::i();

        try {
            if ($uuid) {
                $resumed = $app->repo(Log::class)->findOneBy(['uuid' => $uuid]);

                if ($resumed) {
                    return $resumed;
                }
            }

            $log = new Log;
            $log->uuid = $uuid ?: $this->newUuid();
            $log->opportunityId = $opportunity->id;
            $log->outcome = SendOutcome::PENDING;
            $log->triggeredByUser = $this->authorOfTheRequest();

            $app->em->persist($log);
            $app->em->flush();

            // só depois de o novo existir: abandonar antes deixaria a oportunidade sem envio em curso se o insert falhasse
            $this->abandonPendingOf((int) $opportunity->id, (int) $log->id);

            return $log;
        } catch (Throwable $error) {
            $this->report($opportunity->id, $error);

            return null;
        }
    }

    /** Grava uma troca como tentativa do envio. */
    public function recordAttempt(Log $log, HttpExchange $exchange, string $outcome, int $attempt, int $maxAttempts): ?LogAttempt
    {
        $app = App::i();

        try {
            $logAttempt = new LogAttempt;
            $logAttempt->log = $log;
            $logAttempt->attempt = $attempt;
            $logAttempt->maxAttempts = $maxAttempts;
            $logAttempt->endpoint = (string) $this->sanitized($exchange->endpoint);
            $logAttempt->httpMethod = $exchange->method;
            $logAttempt->httpStatus = $exchange->status;
            $logAttempt->requestId = $this->sanitized($exchange->requestId, self::MAX_REQUEST_ID_BYTES);
            $logAttempt->requestPayload = $this->encoded($exchange->requestPayload);
            $logAttempt->responseBody = $this->sanitized($exchange->responseBody);
            $logAttempt->responseHeaders = $this->encoded($exchange->responseHeaders);
            $logAttempt->errorMessage = $this->sanitized($exchange->errorMessage);
            $logAttempt->outcome = $outcome;
            $logAttempt->sentAt = new DateTime;
            $logAttempt->durationMs = $exchange->durationMs;

            $app->em->persist($logAttempt);
            $app->em->flush();

            return $logAttempt;
        } catch (Throwable $error) {
            $this->report($log->opportunityId, $error);

            return null;
        }
    }

    /**
     * Fecha o envelope com o desfecho, se ele ainda estiver em andamento.
     *
     * @return bool false quando outro processo já fechou, ou quando a guarda recusa o desfecho
     */
    public function finish(Log $log, string $outcome): bool
    {
        $app = App::i();

        try {
            if (!in_array($outcome, self::FINAL_OUTCOMES, true)) {
                $app->log->error("ConectaEnte: desfecho '{$outcome}' não fecha o histórico do edital {$log->opportunityId}.");

                return false;
            }

            if ($outcome === SendOutcome::SUCCESS && !$this->hasAnsweredAttempt($log)) {
                $app->log->error("ConectaEnte: histórico do edital {$log->opportunityId} sem tentativa respondida, não fecha como sucesso.");

                return false;
            }

            $closed = (int) $app->em->createQuery(
                'UPDATE ' . Log::class . ' l
                    SET l.outcome = :outcome, l.updateTimestamp = :now
                  WHERE l.id = :id AND l.outcome = :pending'
            )->execute([
                'outcome' => $outcome,
                'now' => new DateTime,
                'id' => $log->id,
                'pending' => SendOutcome::PENDING,
            ]);

            if ($closed !== 1) {
                $app->log->warning("ConectaEnte: histórico do edital {$log->opportunityId} já havia sido fechado.");

                return false;
            }

            $app->em->refresh($log);

            return true;
        } catch (Throwable $error) {
            $this->report($log->opportunityId, $error);

            return false;
        }
    }

    // UPDATE condicional, nunca ler-alterar-gravar: dois workers leriam o mesmo conjunto pendente e o flush levaria o que estivesse em memória
    private function abandonPendingOf(int $opportunityId, int $currentLogId): void
    {
        try {
            App::i()->em->createQuery(
                'UPDATE ' . Log::class . ' l
                    SET l.outcome = :abandoned, l.updateTimestamp = :now
                  WHERE l.opportunityId = :opportunityId
                    AND l.outcome = :pending
                    AND l.id <> :currentLogId'
            )->execute([
                'abandoned' => SendOutcome::ABANDONED,
                'now' => new DateTime,
                'opportunityId' => $opportunityId,
                'pending' => SendOutcome::PENDING,
                'currentLogId' => $currentLogId,
            ]);
        } catch (Throwable $error) {
            $this->report($opportunityId, $error);
        }
    }

    // consulta direta, não $log->attempts: a coleção inversa não enxerga a tentativa gravada na mesma sessão
    private function hasAnsweredAttempt(Log $log): bool
    {
        $attempts = App::i()->repo(LogAttempt::class)->findBy(['log' => $log]);

        foreach ($attempts as $attempt) {
            if ($attempt->httpStatus !== null) {
                return true;
            }
        }

        return false;
    }

    // na retentativa o job roda sem sessão: quem disparou continua sendo quem salvou a oportunidade
    private function authorOfTheRequest(): ?User
    {
        $user = App::i()->user;

        return $user instanceof User ? $user : null;
    }

    private function sanitized(?string $value, int $maxBytes = self::MAX_STORED_BYTES): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strcut($this->scrubbed($value), 0, $maxBytes, 'UTF-8');
    }

    private function encoded(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? null : $this->sanitized($json);
    }

    // o Postgres recusa byte inválido até em coluna text; mb_scrub lê o substituto da configuração global do mbstring
    private function scrubbed(string $value): string
    {
        $previous = mb_substitute_character();
        mb_substitute_character(0xFFFD);

        try {
            return mb_scrub($value, 'UTF-8');
        } finally {
            mb_substitute_character($previous);
        }
    }

    private function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function report(?int $opportunityId, Throwable $error): void
    {
        App::i()->log->error("ConectaEnte: falha ao gravar o histórico do edital {$opportunityId}: {$error->getMessage()}");
    }
}
