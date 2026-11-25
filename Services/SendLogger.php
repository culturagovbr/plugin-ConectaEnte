<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\Log;
use ConectaEnte\Entities\LogAttempt;
use ConectaEnte\Http\HttpExchange;
use ConectaEnte\Http\SendAcceptance;
use DateTime;
use Doctrine\DBAL\LockMode;
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
    // contado do início do job, ou da abertura do envelope se o job ainda não começou
    const RESULT_LOCK_SECONDS = 600;
    // menor que a trava: o POST dura até um minuto e não pode estar em voo quando ela vence
    const RESULT_POST_DEADLINE_SECONDS = 480;
    const MAX_REQUEST_ID_BYTES = 255;

    private const JSON_FLAGS = JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

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
            $this->abandonPendingOpportunitySendsOf((int) $opportunity->id, (int) $log->id);

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
            $logAttempt->responseBody = $this->sanitized($log->action === Log::ACTION_RESULT ? $this->withoutReceivedValues($exchange->responseBody) : $exchange->responseBody);
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

    // a régua do cliente; aceite com envio em erro é o id recusado depois da resposta, e a tentativa acompanha
    private function outcomeOf(Log $log, HttpExchange $exchange, string $sendOutcome): string
    {
        $operation = $log->action === Log::ACTION_RESULT ? SendAcceptance::RESULT : SendAcceptance::OPPORTUNITY;

        return match (true) {
            $exchange->status === null => $sendOutcome,
            SendAcceptance::isAccepted($operation, $exchange->status, $exchange->responseBody)
                => $sendOutcome === SendOutcome::ERROR ? SendOutcome::ERROR : SendOutcome::SUCCESS,
            $operation === SendAcceptance::OPPORTUNITY && $exchange->status === 200 => SendOutcome::ERROR,
            $exchange->status >= 500 => SendOutcome::ERROR,
            default => SendOutcome::REJECTED,
        };
    }

    /** Guarda no envelope o id que o CultBR devolveu, para correlacionar os dois lados sem abrir o payload. */
    public function keepParEdital(Log $log, int $parEditalId): void
    {
        try {
            $log->parEditalId = $parEditalId;
            App::i()->em->flush();
        } catch (Throwable $error) {
            $this->report($log->opportunityId, $error);
        }
    }

    /**
     * Grava uma tentativa por troca da execução, na ordem em que aconteceram.
     *
     * O desfecho de cada uma sai da faixa de status; o do envio só vale para a troca que não teve resposta.
     *
     * @param HttpExchange[] $exchanges
     * @return LogAttempt[] as que foram gravadas
     */
    public function recordAttempts(Log $log, array $exchanges, string $outcome, int $attempt, int $maxAttempts): array
    {
        $stored = [];

        foreach ($exchanges as $exchange) {
            $recorded = $this->recordAttempt($log, $exchange, $this->outcomeOf($log, $exchange, $outcome), $attempt, $maxAttempts);

            if ($recorded) {
                $stored[] = $recorded;
            }
        }

        return $stored;
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
    private function abandonPendingOpportunitySendsOf(int $opportunityId, int $currentLogId): void
    {
        try {
            App::i()->em->createQuery(
                'UPDATE ' . Log::class . ' l
                    SET l.outcome = :abandoned, l.updateTimestamp = :now
                  WHERE l.opportunityId = :opportunityId
                    AND l.action = :action
                    AND l.outcome = :pending
                    AND l.id <> :currentLogId'
            )->execute([
                'abandoned' => SendOutcome::ABANDONED,
                'now' => new DateTime,
                'opportunityId' => $opportunityId,
                'action' => Log::ACTION_OPPORTUNITY,
                'pending' => SendOutcome::PENDING,
                'currentLogId' => $currentLogId,
            ]);
        } catch (Throwable $error) {
            $this->report($opportunityId, $error);
        }
    }

    /**
     * Abre o envelope do envio de resultado, ou null quando outro do mesmo edital ainda está no prazo.
     */
    public function openResultSend(Opportunity $opportunity): ?Log
    {
        try {
            return $this->openResultSendOrYield($opportunity);
        } catch (Throwable $error) {
            $this->report($opportunity->id, $error);

            return null;
        }
    }

    private function openResultSendOrYield(Opportunity $opportunity): ?Log
    {
        // o resultado não é upsert: o envelope pendente não é abandonado por outro, ele trava o seguinte
        if ($this->pendingResultSend($opportunity)) {
            return null;
        }

        $app = App::i();
        $log = new Log;
        $log->uuid = $this->newUuid();
        $log->opportunityId = $opportunity->id;
        $log->action = Log::ACTION_RESULT;
        $log->outcome = SendOutcome::PENDING;
        $log->triggeredByUser = $this->authorOfTheRequest();
        $app->em->persist($log);
        $app->em->flush();

        // dois cliques no mesmo instante abrem dois envelopes: o mais novo cede ao mais antigo
        $oldest = $this->oldestPendingResultSend((int) $opportunity->id);
        $yielded = $oldest && $oldest->id < $log->id
            && $this->changePending($log, fn() => true, fn() => $log->outcome = SendOutcome::ABANDONED);

        return $yielded ? null : $log;
    }

    /** O envio de resultado do edital que ainda está em andamento, se houver; o que passou do prazo é fechado antes. */
    public function pendingResultSend(Opportunity $opportunity): ?Log
    {
        $this->expireStaleResultSends((int) $opportunity->id);

        return $this->oldestPendingResultSend((int) $opportunity->id);
    }

    /** Marca o início do job no envelope; false se ele já não está pendente ou já foi começado. */
    public function claimResultSend(Log $log): bool
    {
        return $this->changePending($log, fn() => $log->startedAt === null, fn() => $log->startedAt = new DateTime);
    }

    /** Imediatamente antes do POST: confirma que o envelope segue pendente e no prazo de postar, e o marca como enviado. */
    public function confirmResultPost(Log $log): bool
    {
        $limit = $this->secondsAgo(self::RESULT_POST_DEADLINE_SECONDS);

        return $this->changePending($log, fn() => $log->startedAt !== null && $log->startedAt >= $limit, fn() => $log->posted = true);
    }

    /** Guarda no envelope o que o envio de resultado precisa para a conciliação do seguinte. */
    public function keepResultContext(Log $log, ?int $seenResultId, bool $seenResultRead, string $contentHash, int $resultType, string $publicationDate): void
    {
        try {
            $log->seenResultId = $seenResultId;
            $log->seenResultRead = $seenResultRead;
            $log->contentHash = $contentHash;
            $log->resultType = $resultType;
            $log->publicationDate = $publicationDate;
            App::i()->em->flush();
        } catch (Throwable $error) {
            $this->report($log->opportunityId, $error);
        }
    }

    private function oldestPendingResultSend(int $opportunityId): ?Log
    {
        return App::i()->repo(Log::class)->findOneBy($this->pendingResultSendsOf($opportunityId), ['id' => 'ASC']);
    }

    private function pendingResultSendsOf(int $opportunityId): array
    {
        return ['opportunityId' => $opportunityId, 'action' => Log::ACTION_RESULT, 'outcome' => SendOutcome::PENDING];
    }

    // a condição é conferida com a linha bloqueada e relida: de dois processos, só um a vê valer
    private function changePending(Log $log, callable $allowed, callable $change): bool
    {
        $em = App::i()->em;
        $em->beginTransaction();

        try {
            $em->find(Log::class, $log->id, LockMode::PESSIMISTIC_WRITE);
            $changes = $log->outcome === SendOutcome::PENDING && $allowed();

            if ($changes) {
                $change();
                $log->updateTimestamp = new DateTime;
                // só o envelope: o flush geral levaria o que mais estivesse em memória
                $em->flush($log);
            }

            $em->commit();

            return $changes;
        } catch (Throwable $error) {
            $em->rollback();
            $this->report($log->opportunityId, $error);

            return false;
        }
    }

    // vencido não é abandonado: fecha em erro, sem desfecho conhecido, e o envio seguinte passa pela conciliação
    private function expireStaleResultSends(int $opportunityId): void
    {
        $limit = $this->secondsAgo(self::RESULT_LOCK_SECONDS);

        foreach (App::i()->repo(Log::class)->findBy($this->pendingResultSendsOf($opportunityId)) as $log) {
            $this->changePending($log, fn() => ($log->startedAt ?? $log->createTimestamp) < $limit, fn() => $log->outcome = SendOutcome::ERROR);
        }
    }

    private function secondsAgo(int $seconds): DateTime
    {
        return new DateTime("-{$seconds} seconds");
    }

    // o valor recebido de uma linha recusada pode ser o CPF de um contemplado
    private function withoutReceivedValues(?string $body): ?string
    {
        $decoded = $body === null ? null : json_decode($body, true);

        if (!is_array($decoded) || !isset($decoded['inconsistencias']) || !is_array($decoded['inconsistencias'])) {
            return $body;
        }

        $decoded['inconsistencias'] = array_map(fn($item) => is_array($item) ? array_diff_key($item, ['valor_recebido' => true]) : $item, $decoded['inconsistencias']);

        return json_encode($decoded, self::JSON_FLAGS);
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

        // referência, não a instância: depois de um clear do EntityManager ela está destacada e o flush recusa
        return $user instanceof User ? App::i()->em->getReference(User::class, $user->id) : null;
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

        $json = json_encode($this->withShortenedTexts($value), self::JSON_FLAGS);

        if ($json === false) {
            return null;
        }

        // cortar JSON por bytes devolve texto que ninguém consegue ler de volta: o que não couber vira marcador
        return strlen($json) <= self::MAX_STORED_BYTES
            ? $json
            : json_encode(['truncated' => true, 'bytes' => strlen($json)], self::JSON_FLAGS);
    }

    private function withShortenedTexts(array $value): array
    {
        return array_map(fn ($item) => match (true) {
            is_array($item) => $this->withShortenedTexts($item),
            is_string($item) => $this->sanitized($item),
            default => $item,
        }, $value);
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
