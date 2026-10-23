<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\Log;
use ConectaEnte\Entities\LogAttempt;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;

/**
 * A leitura do histórico de envio de uma oportunidade, paginada.
 */
class SendHistory
{
    const DEFAULT_PAGE_SIZE = 20;
    const MAX_PAGE_SIZE = 50;

    /**
     * Envios da oportunidade, mais recentes primeiro, com as tentativas aninhadas.
     */
    public function of(Opportunity $opportunity, int $page = 1, int $pageSize = self::DEFAULT_PAGE_SIZE): array
    {
        $app = App::i();
        $size = min(self::MAX_PAGE_SIZE, max(1, $pageSize));
        $page = max(1, $page);

        // o carimbo tem precisão de segundo: sem o id no desempate, dois envios do mesmo segundo trocam de lugar entre as páginas
        $logs = $app->repo(Log::class)->findBy(
            ['opportunityId' => $opportunity->id],
            ['createTimestamp' => 'DESC', 'id' => 'DESC'],
            $size,
            ($page - 1) * $size,
        );

        $attempts = $this->attemptsGroupedByLog($logs);

        return [
            'total' => $app->repo(Log::class)->count(['opportunityId' => $opportunity->id]),
            'page' => $page,
            'pageSize' => $size,
            'sends' => array_map(fn (Log $log) => $this->describe($log, $attempts[$log->id] ?? []), $logs),
        ];
    }

    /**
     * Tentativas de todos os envios da página numa consulta só, indexadas pelo envio.
     *
     * @param Log[] $logs
     */
    private function attemptsGroupedByLog(array $logs): array
    {
        if (!$logs) {
            return [];
        }

        $grouped = [];

        // consulta direta, não $log->attempts: a coleção inversa não enxerga o que foi gravado na mesma sessão
        foreach (App::i()->repo(LogAttempt::class)->findBy(['log' => $logs], ['id' => 'ASC']) as $attempt) {
            $grouped[$attempt->log->id][] = $attempt;
        }

        return $grouped;
    }

    /** @param LogAttempt[] $attempts */
    private function describe(Log $log, array $attempts): array
    {
        return [
            'uuid' => $log->uuid,
            'outcome' => $log->outcome,
            'parEditalId' => $log->parEditalId,
            // só o id de quem disparou: nome e e-mail não têm o que fazer no histórico de integração
            'triggeredByUserId' => $log->triggeredByUser?->id,
            'startedAt' => $log->createTimestamp?->format('Y-m-d H:i:s'),
            'finishedAt' => $log->updateTimestamp?->format('Y-m-d H:i:s'),
            'attempts' => array_map(fn (LogAttempt $attempt) => [
                'attempt' => $attempt->attempt,
                'maxAttempts' => $attempt->maxAttempts,
                'endpoint' => $attempt->endpoint,
                'httpMethod' => $attempt->httpMethod,
                'httpStatus' => $attempt->httpStatus,
                'requestId' => $attempt->requestId,
                'outcome' => $attempt->outcome,
                'sentAt' => $attempt->sentAt?->format('Y-m-d H:i:s'),
                'durationMs' => $attempt->durationMs,
                'requestPayload' => $attempt->requestPayload,
                'responseBody' => $attempt->responseBody,
                'responseHeaders' => $attempt->responseHeaders,
                'errorMessage' => $attempt->errorMessage,
            ], $attempts),
        ];
    }
}
