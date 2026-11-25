<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\Log;
use ConectaEnte\Entities\LogAttempt;
use ConectaEnte\Http\ApiErrors;
use ConectaEnte\Http\HttpExchange;
use ConectaEnte\Http\PublishedResult;
use ConectaEnte\Http\ResultSendResult;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Payload\ResultPayload;
use ConectaEnte\Plugin;
use DateTime;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\i;

/**
 * O envio do resultado pelo job: lê o publicado, concilia o envio anterior sem desfecho, decide o tipo e posta uma vez.
 */
final class ResultSender
{
    const STATUS_SUCCESS = 'success';
    const STATUS_REJECTED = 'rejected';
    const STATUS_ERROR = 'error';
    const STATUS_SIMULATED = 'simulated';
    const STATUS_UNCERTAIN = 'uncertain';
    const MAX_STORED_ERRORS = 200;
    const DOCUMENT_KEYS = ['cpf', 'cnpj', 'documento', 'numero_documento'];

    public function __construct(private Plugin $plugin)
    {
    }

    /** Executa o envio que a rota enfileirou; nunca lança, porque o job que lança prende a linha da fila. */
    public function send(Opportunity $opportunity, Log $log): void
    {
        $logger = $this->plugin->sendLogger();

        if (!$logger->claimResultSend($log)) {
            return;
        }

        try {
            $this->sendClaimed($opportunity, $log);
        } catch (\Throwable $error) {
            App::i()->log->error("ConectaEnte: exceção no envio do resultado do edital {$opportunity->id}: {$error->getMessage()}");
            $this->closeLocally($opportunity, $log, i::__('Falha ao enviar o resultado ao CultBR.'));
        }
    }

    /**
     * Lê o publicado e resolve o envio anterior que terminou sem resposta conclusiva.
     *
     * @return PublishedResult|null o publicado lido, ou null quando a leitura não foi conclusiva
     */
    public function reconcile(Opportunity $opportunity, bool $queued = false): ?PublishedResult
    {
        $token = $this->plugin->sealedOpportunity()->federativeEntityOf($opportunity)?->token;
        $published = $this->plugin->client()->getPublishedResult((string) $token, (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID), $queued);

        if (!$published->known) {
            return null;
        }

        $unsettled = $this->unsettledSend($opportunity);

        if (!$unsettled) {
            return $published;
        }

        // apareceu um id novo depois do POST sem resposta: é o envio que chegou
        if ($published->resultId !== $unsettled->seenResultId) {
            $this->recordSuccess($opportunity, $published->resultId, (string) $unsettled->contentHash, (int) $unsettled->resultType);
        } else {
            $this->recordStatus($opportunity, self::STATUS_ERROR, i::__('O envio anterior não chegou ao CultBR.'));
        }

        $unsettled->reconciledAt = new DateTime();
        App::i()->em->flush();

        return $published;
    }

    private function sendClaimed(Opportunity $opportunity, Log $log): void
    {
        $state = $this->plugin->resultService()->stateOf($opportunity);

        // aqui o estado é sempre "em envio", pelo próprio envelope: o que se confere é o formulário
        if (!$state->isFormComplete()) {
            $this->closeLocally($opportunity, $log, i::__('O resultado deixou de estar completo antes do envio.'));
            return;
        }

        $unsettled = $this->unsettledSend($opportunity);
        $published = $this->reconcile($opportunity, queued: true);

        if (!$published) {
            $this->closeLocally($opportunity, $log, i::__('Não foi possível saber se o edital já tem resultado publicado no CultBR. Nada foi enviado.'));
            return;
        }

        if ($unsettled && $published->resultId !== $unsettled->seenResultId) {
            $this->closeLocally($opportunity, $log, i::__('O envio anterior chegou ao CultBR e foi reconhecido. Nada foi enviado de novo.'));
            return;
        }

        $type = $published->exists() ? ResultPayload::TYPE_CORRECTED : ResultPayload::TYPE_FINAL;
        $built = $this->plugin->resultPayload()->build($opportunity, $this->plugin->resultService()->formOf($opportunity, $state->structure), $type);

        if ($built['error'] || $this->isAlreadySent($opportunity, $built['hash'])) {
            $this->closeLocally($opportunity, $log, $built['error'] ?? i::__('Nada mudou desde o último resultado enviado.'));
            return;
        }

        $this->plugin->sendLogger()->keepResultContext($log, $published->resultId, true, $built['hash'], $type, $built['payload']['data_publicacao']);

        if (!$this->plugin->sendLogger()->confirmResultPost($log)) {
            $this->closeLocally($opportunity, $log, i::__('O envio passou do prazo antes de sair e foi cancelado.'));
            return;
        }

        if ($this->plugin->isDevMode()) {
            $this->recordStatus($opportunity, self::STATUS_SIMULATED, null);
            $this->closeLocally($opportunity, $log, null, SendOutcome::SIMULATED);
            return;
        }

        $this->post($opportunity, $log, $built, $type, $published);
    }

    private function post(Opportunity $opportunity, Log $log, array $built, int $type, PublishedResult $published): void
    {
        $token = $this->plugin->sealedOpportunity()->federativeEntityOf($opportunity)->token;
        $parEditalId = (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID);
        $recorded = $built['payload'];

        // as linhas trazem CPF e CNPJ de terceiros: no histórico ficam só o total e o hash
        if (isset($recorded['linhas'])) {
            $recorded['linhas'] = ['total' => $built['totalLines'], 'hash' => $built['linesHash']];
        }

        $recorded['respostas'] = $this->withoutDocuments($recorded['respostas'] ?? []);

        $client = $this->plugin->client();
        $result = $client->sendResult($token, $parEditalId, $built['payload'], $recorded, $built['files']);
        $outcome = $this->outcomeOf($result);

        $this->plugin->sendLogger()->recordAttempts($log, $client->exchanges(), $outcome, 1, 1);

        match (true) {
            $result->accepted => $this->recordSuccess($opportunity, $result->resultId() ?? $this->recoveredId($opportunity, $published), $built['hash'], $type),
            $result->unreachable => $this->recordStatus($opportunity, self::STATUS_UNCERTAIN, $result->message ?: i::__('O CultBR não respondeu ao envio do resultado.')),
            default => $this->recordRejection($opportunity, $result, $built['lineRegistrationIds']),
        };

        $this->plugin->sendLogger()->finish($log, $outcome);
    }

    // as tabelas da comissão e dos contratados também trazem documento de terceiros
    private function withoutDocuments(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->withoutDocuments($item);
            } elseif (in_array($key, self::DOCUMENT_KEYS, true) && $item !== null && $item !== '') {
                $value[$key] = '***';
            }
        }

        return $value;
    }

    private function outcomeOf(ResultSendResult $result): string
    {
        return match (true) {
            $result->accepted => SendOutcome::SUCCESS,
            $result->unreachable => SendOutcome::ERROR,
            default => SendOutcome::REJECTED,
        };
    }

    // o 201 sem id é sucesso: recusar faria o clique seguinte publicar de novo
    private function recoveredId(Opportunity $opportunity, PublishedResult $before): ?int
    {
        $token = $this->plugin->sealedOpportunity()->federativeEntityOf($opportunity)->token;
        $after = $this->plugin->client()->getPublishedResult($token, (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID), true);

        if ($after->known && $after->resultId !== $before->resultId) {
            return $after->resultId;
        }

        App::i()->log->warning("ConectaEnte: o CultBR aceitou o resultado do edital {$opportunity->id} sem devolver o id, e o publicado não o mostrou.");

        return null;
    }

    private function recordSuccess(Opportunity $opportunity, ?int $resultId, string $hash, int $type): void
    {
        App::i()->mscache->delete(ResultService::PUBLISHED_CACHE_PREFIX . (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID));

        $opportunity->{CultBrMetadata::RESULT_ID} = $resultId === null ? null : (string) $resultId;
        $opportunity->{CultBrMetadata::RESULT_TYPE} = (string) $type;
        $opportunity->{CultBrMetadata::RESULT_SENT_HASH} = ['hash' => $hash, 'parEditalId' => (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID)];
        $this->recordStatus($opportunity, self::STATUS_SUCCESS, null);
    }

    // a lista do 422 fica no edital para a aba; sem o valor recebido, que pode ser CPF, e com a inscrição no lugar do índice
    private function recordRejection(Opportunity $opportunity, ResultSendResult $result, array $lineRegistrationIds): void
    {
        $errors = $result->errors ?? ApiErrors::fromBody([]);
        $rows = array_map(
            fn($row) => array_diff_key($row, ['valor_recebido' => true, 'indice' => true]) + ['registrationId' => $lineRegistrationIds[$row['indice'] ?? -1] ?? null],
            array_slice($errors->rows, 0, self::MAX_STORED_ERRORS),
        );

        $opportunity->{CultBrMetadata::RESULT_SEND_ERRORS} = [
            'status' => $result->status,
            'fields' => $errors->fields,
            'rows' => $rows,
            'totalRows' => $errors->totalRows ?? count($errors->rows),
            'detail' => $errors->detail,
        ];
        $this->recordStatus($opportunity, self::STATUS_REJECTED, $result->message);
    }

    private function recordStatus(Opportunity $opportunity, string $status, ?string $reason): void
    {
        if ($status !== self::STATUS_REJECTED) {
            $opportunity->{CultBrMetadata::RESULT_SEND_ERRORS} = null;
        }

        $opportunity->{CultBrMetadata::RESULT_SEND_STATUS} = $status;
        $opportunity->{CultBrMetadata::RESULT_SEND_REASON} = $reason;
        $opportunity->{CultBrMetadata::RESULT_SEND_AT} = (new DateTime())->format('Y-m-d H:i:s');
        // saveMetadata, nunca save: salvar a oportunidade selada reenviaria o edital
        $opportunity->saveMetadata();
    }

    // recusa do próprio plugin, antes do POST: o envelope fecha sem tentativa ao CultBR
    private function closeLocally(Opportunity $opportunity, Log $log, ?string $reason, string $outcome = SendOutcome::ERROR): void
    {
        $logger = $this->plugin->sendLogger();
        $logger->recordAttempt($log, new HttpExchange(endpoint: $this->plugin->client()->sendResultUrl((int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID)), errorMessage: $reason), $outcome, 1, 1);

        if ($outcome === SendOutcome::ERROR) {
            $this->recordStatus($opportunity, self::STATUS_ERROR, $reason);
        }

        $logger->finish($log, $outcome);
    }

    /** Se o edital, com o id atual no CultBR, já tem resultado enviado por aqui. */
    public function hasSentResult(Opportunity $opportunity): bool
    {
        $sent = json_decode(json_encode($opportunity->{CultBrMetadata::RESULT_SENT_HASH}), true);

        return is_array($sent) && (int) ($sent['parEditalId'] ?? 0) === (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID);
    }

    /** Se este conteúdo já foi o último enviado para o id atual do edital. */
    public function isAlreadySent(Opportunity $opportunity, string $hash): bool
    {
        $sent = json_decode(json_encode($opportunity->{CultBrMetadata::RESULT_SENT_HASH}), true);

        return is_array($sent) && ($sent['hash'] ?? null) === $hash && (int) ($sent['parEditalId'] ?? 0) === (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID);
    }

    /** O último envio que fez POST e fechou sem 2xx nem 4xx, com leitura prévia do publicado: o único que se concilia. */
    public function unsettledSend(Opportunity $opportunity): ?Log
    {
        $last = App::i()->repo(Log::class)->findOneBy(['opportunityId' => $opportunity->id, 'action' => Log::ACTION_RESULT, 'posted' => true], ['id' => 'DESC']);

        if (!$last || $last->outcome !== SendOutcome::ERROR || !$last->seenResultRead || $last->reconciledAt) {
            return null;
        }

        $answered = App::i()->repo(LogAttempt::class)->findOneBy(['log' => $last], ['id' => 'DESC']);
        $status = $answered?->httpStatus;

        return $status === null || $status >= 500 ? $last : null;
    }
}
