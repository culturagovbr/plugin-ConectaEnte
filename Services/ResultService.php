<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Http\ApiRead;
use ConectaEnte\Http\SendResult;
use ConectaEnte\Jobs\SendResultJob;
use ConectaEnte\Payload\ResultPayload;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Registration;
use MapasCulturais\i;

final class ResultService
{
    const STRUCTURE_CACHE_SECONDS = 300;
    const REQUEST_TIME_LIMIT_SECONDS = 10;
    const PUBLISHED_CACHE_PREFIX = 'conectaente:result-published:';
    const PUBLISHED_CACHE_SECONDS = 60;

    // arquivado segue: o edital encerrado ainda presta contas do resultado
    const STATUSES_WITH_RESULT = [Opportunity::STATUS_ENABLED, Opportunity::STATUS_ARCHIVED];

    public function __construct(private Plugin $plugin)
    {
    }

    /**
     * O estado do resultado, conferindo antes o que é local e só depois o que exige o CultBR.
     */
    public function stateOf(Opportunity $opportunity): ResultState
    {
        $federativeEntity = $opportunity->parent ? null : $this->plugin->sealedOpportunity()->federativeEntityOf($opportunity);

        if (!$federativeEntity) {
            return ResultState::of(ResultState::NO_TAB);
        }

        return $this->localBlock($opportunity, $federativeEntity) ?? $this->remoteState($opportunity, $federativeEntity);
    }

    /** A estrutura do formulário lida agora no CultBR, sem cache: é a que vale no envio. */
    public function freshStructure(Opportunity $opportunity): ApiRead
    {
        $federativeEntity = $this->plugin->sealedOpportunity()->federativeEntityOf($opportunity);
        $read = $this->plugin->client()->getResultFormStructure($federativeEntity->token, (int) $opportunity->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID));

        if ($read->body !== null) {
            App::i()->mscache->save($this->structureCacheKey($opportunity), $read->body, self::STRUCTURE_CACHE_SECONDS);
        }

        return $read;
    }

    private function localBlock(Opportunity $opportunity, FederativeEntity $federativeEntity): ?ResultState
    {
        $sentBy = $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_FEDERATIVE_ENTITY_DOCUMENT);

        return match (true) {
            !in_array((int) $opportunity->status, self::STATUSES_WITH_RESULT, true) => ResultState::of(ResultState::UNAVAILABLE, 'status'),
            !$opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID) => ResultState::of(ResultState::NO_PAR_EDITAL),
            $sentBy && $sentBy !== $federativeEntity->document => ResultState::of(ResultState::FEDERATIVE_ENTITY_CHANGED),
            !$opportunity->lastPhase?->publishedRegistrations => ResultState::of(ResultState::AWAITING_FINAL_PUBLICATION),
            default => null,
        };
    }

    private function remoteState(Opportunity $opportunity, FederativeEntity $federativeEntity): ResultState
    {
        $actionTypeRead = $this->ensureActionType($opportunity, $federativeEntity);

        if ($actionTypeRead) {
            return ResultState::of(ResultState::CULTBR_UNAVAILABLE, $this->unavailableReason($actionTypeRead));
        }

        $cached = App::i()->mscache->fetch($this->structureCacheKey($opportunity));
        $read = is_array($cached) ? ApiRead::ok($cached) : $this->freshStructure($opportunity);

        return match (true) {
            $read->body !== null => $this->formState($opportunity, $read->body),
            $read->notFound => ResultState::of(ResultState::NO_FORM),
            default => ResultState::of(ResultState::CULTBR_UNAVAILABLE, $this->unavailableReason($read)),
        };
    }

    /** A estrutura lida como formulário, sabendo se o edital leva planilha. */
    public function formOf(Opportunity $opportunity, array $structure): ResultForm
    {
        return new ResultForm($structure, $this->plugin->resultSpreadsheet()->isRequired($opportunity));
    }

    private function formState(Opportunity $opportunity, array $structure): ResultState
    {
        $form = $this->formOf($opportunity, $structure);
        $answers = $this->plugin->resultAnswers();
        $sendState = $this->sendState($opportunity);
        $published = $sendState === null && $this->hasPublishedResult($opportunity);

        return ResultState::withForm($structure, [
            'published' => $published,
            'dispensed' => $form->dispensedIds(),
            'filledByLines' => $form->filledByLinesIds(),
            'unavailableAttachments' => $form->unavailableAttachmentIds(),
            'lastSend' => $this->lastSend($opportunity, $form),
            'answers' => (object) $answers->forScreen($opportunity, $form),
            'committee' => (object) $answers->committee($opportunity, $form),
            'computed' => (object) $answers->computed($opportunity, $form),
            'suggested' => (object) $answers->suggested($opportunity, $form),
            'mirrored' => (object) $answers->mirrored($opportunity, $form),
            'missing' => $answers->missing($opportunity, $form),
            'stale' => $answers->isStale($opportunity, $form),
            'spreadsheetPending' => $this->plugin->resultRows()->pendingRowCount($opportunity),
        ], $sendState, $published);
    }

    // o publicado enxerga resultado criado por fora; sem leitura conclusiva, vale o que foi aceito por aqui
    private function hasPublishedResult(Opportunity $opportunity): bool
    {
        $key = self::PUBLISHED_CACHE_PREFIX . (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID);
        $cached = App::i()->mscache->fetch($key);

        if (is_bool($cached)) {
            return $cached;
        }

        $token = $this->plugin->sealedOpportunity()->federativeEntityOf($opportunity)?->token;
        $published = $this->plugin->client()->getPublishedResult((string) $token, (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID));

        $exists = $published->known ? $published->exists() : $this->plugin->resultSender()->hasSentResult($opportunity);
        App::i()->mscache->save($key, $exists, self::PUBLISHED_CACHE_SECONDS);

        return $exists;
    }

    // em envio esconde o botão; incerto só sai com uma leitura determinada do publicado, que concilia o envio sem resposta
    private function sendState(Opportunity $opportunity): ?string
    {
        $sender = $this->plugin->resultSender();

        if ($this->plugin->sendLogger()->pendingResultSend($opportunity)) {
            return ResultState::SENDING;
        }

        if (!$sender->unsettledSend($opportunity)) {
            return null;
        }

        return $sender->reconcile($opportunity) ? null : ResultState::UNCERTAIN;
    }

    /**
     * Confere o pedido de envio e o enfileira; devolve a recusa, ou a confirmação de que foi para a fila.
     *
     * @return array{queued: bool, message: ?string, missing: int[], pendingRows: int} a mensagem acompanha a fila quando a conferência ficou para o job
     */
    public function requestSend(Opportunity $opportunity): array
    {
        $startedAt = microtime(true);
        $state = $this->stateOf($opportunity);
        $refusal = fn(string $message) => ['queued' => false, 'message' => $message, 'missing' => $state->form['missing'] ?? [], 'pendingRows' => (int) ($state->form['spreadsheetPending'] ?? 0)];

        $message = match ($state->state) {
            ResultState::COMPLETE, ResultState::SENT => null,
            ResultState::SENDING => i::__('Um envio deste resultado já está em andamento.'),
            ResultState::UNCERTAIN => i::__('O último envio pode ter chegado ao CultBR. Confira lá antes de enviar de novo.'),
            ResultState::PENDING => i::__('O resultado tem pendências. Complete o formulário e a planilha antes de enviar.'),
            default => i::__('O resultado deste edital não pode ser enviado agora.'),
        };

        if ($message) {
            return $refusal($message);
        }

        // acima do teto a conferência do conteúdo fica para o job, que a repete antes do POST
        $deferred = microtime(true) - $startedAt >= self::REQUEST_TIME_LIMIT_SECONDS;

        if (!$deferred) {
            $built = $this->plugin->resultPayload()->build($opportunity, $this->formOf($opportunity, $state->structure), ResultPayload::TYPE_FINAL);

            if ($built['error']) {
                return $refusal($built['error']);
            }

            if ($this->plugin->resultSender()->isAlreadySent($opportunity, $built['hash'])) {
                return $refusal(i::__('Nada mudou desde o último resultado enviado.'));
            }
        }

        $log = $this->plugin->sendLogger()->openResultSend($opportunity);

        if (!$log) {
            return $refusal(i::__('Um envio deste resultado já está em andamento.'));
        }

        App::i()->enqueueJob(SendResultJob::SLUG, SendResultJob::dataFor($opportunity, $log), 'now', '', 1);

        return ['queued' => true, 'message' => $deferred ? i::__('O resultado foi para a fila. A conferência do conteúdo continua no envio.') : null, 'missing' => [], 'pendingRows' => 0];
    }

    /** O desfecho do último envio de resultado e o resultado vigente, como a aba os mostra. */
    public function lastSend(Opportunity $opportunity, ?ResultForm $form = null): ?array
    {
        $status = $opportunity->getMetadata(CultBrMetadata::RESULT_SEND_STATUS);
        $expired = $this->plugin->sendLogger()->expiredResultSend($opportunity);

        if (!$status && !$expired) {
            return null;
        }

        $sent = [
            'status' => $expired ? ResultSender::STATUS_ERROR : $status,
            'at' => $expired ? $expired->updateTimestamp->format('Y-m-d H:i:s') : $opportunity->getMetadata(CultBrMetadata::RESULT_SEND_AT),
            'reason' => $expired ? i::__('O último pedido de envio não saiu no prazo e foi cancelado. Nada foi enviado; peça o envio de novo.') : $opportunity->getMetadata(CultBrMetadata::RESULT_SEND_REASON),
            'resultId' => $opportunity->getMetadata(CultBrMetadata::RESULT_ID),
            'type' => $opportunity->getMetadata(CultBrMetadata::RESULT_TYPE),
            // só a propriedade passa pelo tipo json
            'errors' => json_decode(json_encode($opportunity->{CultBrMetadata::RESULT_SEND_ERRORS}), true),
        ];

        $rows = $sent['errors']['rows'] ?? [];
        $outcome = $rows ? new ResultOutcome($form, $this->columnLabels($opportunity), $this->registrationNumbers($rows)) : new ResultOutcome($form);

        return $sent + ['outcome' => $outcome->describe($sent)];
    }

    private function registrationNumbers(array $rows): array
    {
        $numbers = [];

        foreach (App::i()->repo(Registration::class)->findBy(['id' => array_filter(array_column($rows, 'registrationId'))]) as $registration) {
            $numbers[$registration->id] = $registration->number;
        }

        return $numbers;
    }

    private function columnLabels(Opportunity $opportunity): array
    {
        $fields = $this->plugin->resultSpreadsheet()->model($opportunity)->body['campos'] ?? [];

        return array_column(array_filter($fields, 'is_array'), 'nome', 'coluna');
    }

    /** Grava as respostas na estrutura que vale agora; devolve o erro de cada uma recusada. */
    public function saveAnswers(Opportunity $opportunity, array $answers): array
    {
        $state = $this->stateOf($opportunity);

        if (!$state->structure) {
            return ['_' => sprintf(i::__('O formulário de resultado não está disponível (%s).'), $state->state)];
        }

        $before = $this->plugin->resultAnswers()->saved($opportunity)['answers'];
        $errors = $this->plugin->resultAnswers()->save($opportunity, $this->formOf($opportunity, $state->structure), $answers);
        $after = $this->plugin->resultAnswers()->saved($opportunity)['answers'];
        $this->settleRefusals($opportunity, 'fields', fn($field) => isset($field['questionId']) && json_encode($before[$field['questionId']] ?? null) !== json_encode($after[$field['questionId']] ?? null));

        return $errors;
    }

    /** Grava a célula da grade; a recusa do CultBR naquela célula deixa de ser marcada. */
    public function saveCell(Opportunity $opportunity, int $registrationId, string $columnKey, mixed $value): ?string
    {
        $error = $this->plugin->resultRows()->saveCell($opportunity, $registrationId, $columnKey, $value);

        if ($error === null) {
            $this->settleRefusals($opportunity, 'rows', fn($row) => (int) ($row['registrationId'] ?? 0) === $registrationId && ($row['coluna'] ?? null) === $columnKey);
        }

        return $error;
    }

    /** Grava o de/para; a recusa do CultBR nas colunas que mudaram deixa de ser marcada. */
    public function saveMapping(Opportunity $opportunity, array $input): array
    {
        $spreadsheet = $this->plugin->resultSpreadsheet();
        $kinds = array_column((array) ($spreadsheet->model($opportunity)->body['campos'] ?? []), 'tipo', 'coluna');
        $sourceOf = fn(array $mapping, string $column) => [
            $mapping['columns'][$column] ?? null,
            $mapping['equivalences'][$column] ?? [],
            array_column($mapping['documents'], $kinds[$column] ?? ''),
        ];

        $before = $spreadsheet->mapping($opportunity);
        $errors = $spreadsheet->save($opportunity, $input);
        $after = $spreadsheet->mapping($opportunity);
        $this->settleRefusals($opportunity, 'rows', fn($row) => isset($row['coluna']) && $sourceOf($before, $row['coluna']) !== $sourceOf($after, $row['coluna']));

        return $errors;
    }

    private function settleRefusals(Opportunity $opportunity, string $kind, callable $isSettled): void
    {
        $errors = json_decode(json_encode($opportunity->{CultBrMetadata::RESULT_SEND_ERRORS}), true);

        if (empty($errors[$kind])) {
            return;
        }

        $settled = array_map(fn($refusal) => $isSettled($refusal) ? ['edited' => true] + $refusal : $refusal, $errors[$kind]);

        if ($settled !== $errors[$kind]) {
            $opportunity->{CultBrMetadata::RESULT_SEND_ERRORS} = [$kind => $settled] + $errors;
            // saveMetadata, nunca save: salvar a oportunidade selada a reenviaria
            $opportunity->saveMetadata();
        }
    }

    /** Anexa o arquivo enviado à questão de anexo, na estrutura que vale agora. */
    public function attach(Opportunity $opportunity, int $questionId, array $upload): ?string
    {
        $state = $this->stateOf($opportunity);

        if (!$state->structure) {
            return sprintf(i::__('O formulário de resultado não está disponível (%s).'), $state->state);
        }

        $error = $this->plugin->resultAttachments()->attach($opportunity, $this->formOf($opportunity, $state->structure), $questionId, $upload);

        if ($error === null) {
            $this->settleRefusals($opportunity, 'fields', fn($field) => (int) ($field['questionId'] ?? 0) === $questionId);
        }

        return $error;
    }

    /** Tira o arquivo da questão de anexo. */
    public function detach(Opportunity $opportunity, int $questionId, int $fileId): void
    {
        $state = $this->stateOf($opportunity);

        if ($state->structure) {
            $this->plugin->resultAttachments()->detach($opportunity, $this->formOf($opportunity, $state->structure), $questionId, $fileId);
        }
    }

    // o tipo de ação só vem no eco do envio do edital; para o acervo sem ele, o modelo de planilha o devolve
    private function ensureActionType(Opportunity $opportunity, FederativeEntity $federativeEntity): ?ApiRead
    {
        if ($opportunity->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID)) {
            return null;
        }

        $read = $this->plugin->client()->getResultSpreadsheetModel($federativeEntity->token, (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID));
        $actionType = SendResult::actionTypeOf($read->body);

        if (!$actionType) {
            return $read->body !== null ? ApiRead::rejected('sem tipo de ação') : $read;
        }

        // saveMetadata, nunca save: salvar a oportunidade selada a reenviaria
        $opportunity->setMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID, (string) $actionType);
        $opportunity->saveMetadata();

        return null;
    }

    private function unavailableReason(ApiRead $read): string
    {
        return $read->unreachable ? 'unreachable' : 'rejected';
    }

    private function structureCacheKey(Opportunity $opportunity): string
    {
        return 'conectaente:result-form:' . (int) $opportunity->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID);
    }
}
