<?php

namespace ConectaEnte\Payload;

use ConectaEnte\Plugin;
use ConectaEnte\Services\ResultForm;
use ConectaEnte\Services\ResultValidator;
use DateTime;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\i;

/**
 * O corpo do resultado no formato que a Conecta Ente aceitou, com os arquivos e o hash do conteúdo.
 */
final class ResultPayload
{
    const STATUS_PUBLISHED = 3;
    const TYPE_FINAL = 2;
    const TYPE_CORRECTED = 3;

    public function __construct(private Plugin $plugin)
    {
    }

    /**
     * Monta o envio; devolve o motivo em `error` quando a planilha tem linha incompleta.
     *
     * @return array{payload: ?array, files: array, hash: ?string, linesHash: ?string, totalLines: int, lineRegistrationIds: int[], error: ?string}
     */
    public function build(Opportunity $opportunity, ResultForm $form, int $type): array
    {
        $answers = $this->plugin->resultAnswers();
        $known = $answers->computed($opportunity, $form) + $answers->saved($opportunity)['answers'];
        $files = $this->plugin->resultAttachments()->files($opportunity);
        $linesByRegistration = $this->lines($opportunity);

        if ($linesByRegistration === false) {
            return ['payload' => null, 'files' => [], 'hash' => null, 'linesHash' => null, 'totalLines' => 0, 'lineRegistrationIds' => [], 'error' => i::__('A planilha de contemplados tem linhas com pendência. Complete a grade antes de enviar.')];
        }

        $lines = $linesByRegistration === null ? null : array_values($linesByRegistration);

        $content = ['respostas' => $this->responses($form, $known, $files)];

        if ($lines) {
            $content['linhas'] = $lines;
        }

        $payload = ['id_tipo' => $type, 'id_status' => self::STATUS_PUBLISHED, 'data_publicacao' => $this->publicationDate($opportunity, $type)] + $content;

        App::i()->applyHook('conectaente.resultPayload', [$opportunity, &$payload]);

        return [
            'payload' => $payload,
            'files' => array_merge([], ...array_values($files)),
            'hash' => $this->hash($content, $files),
            'linesHash' => $lines ? hash('sha256', json_encode($lines)) : null,
            'totalLines' => $lines ? count($lines) : 0,
            'lineRegistrationIds' => $linesByRegistration ? array_keys($linesByRegistration) : [],
            'error' => null,
        ];
    }

    /** No final, a publicação da fase final; sem ela, e no retificado, o instante do envio. */
    public function publicationDate(Opportunity $opportunity, int $type): string
    {
        $finalPublication = $type === self::TYPE_FINAL ? $opportunity->lastPhase?->publishTimestamp : null;

        return ($finalPublication ?: new DateTime())->format(DateTime::ATOM);
    }

    // sem tipo, status e data: são o que muda de um final para um retificado do mesmo conteúdo
    private function hash(array $content, array $files): string
    {
        $content['arquivos'] = array_map(fn($entries) => array_map(fn($file) => md5_file($file['path']) ?: $file['name'], $entries), $files);

        return hash('sha256', json_encode($this->sorted($content), JSON_UNESCAPED_UNICODE));
    }

    private function sorted(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (!array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn($item) => $this->sorted($item), $value);
    }

    /** @return array|false|null as linhas, false quando alguma tem pendência, null sem planilha */
    private function lines(Opportunity $opportunity): array|false|null
    {
        $model = $this->plugin->resultSpreadsheet()->model($opportunity)->body ?? [];

        if (empty($model['possui_planilha'])) {
            return null;
        }

        return $this->plugin->resultRows()->completeRowsByRegistration($opportunity) ?? false;
    }

    private function responses(ResultForm $form, array $known, array $files): array
    {
        $validator = new ResultValidator($form);
        $responses = [];

        foreach ($form->questions() as $id => $question) {
            $value = $this->value($question, $known[$id] ?? null, $files[$id] ?? []);

            // invisível fica fora: o servidor não a cobra; espelho só leitura sem valor também
            if (ResultValidator::isEmpty($value) || !$validator->isVisible($question, $known)) {
                continue;
            }

            $responses[] = ['id_par_questao' => $id, 'resposta' => ['resposta' => $value]];
        }

        return $responses;
    }

    private function value(array $question, mixed $value, array $files): mixed
    {
        $type = $question['tipo_questao'] ?? null;

        return match (true) {
            in_array($type, ResultValidator::ATTACHMENT_TYPES, true) => $this->fileNames($type, $files),
            $type === 'radio' => $this->choiceLabel($question, $value),
            in_array($type, ['múltipla escolha', 'checkbox'], true) => is_array($value) ? array_values(array_filter(array_map(fn($choice) => $this->choiceLabel($question, $choice), $value))) : null,
            $type === 'data' && is_string($value) => substr($value, 0, 10),
            in_array($type, ['número', 'moeda'], true) && is_numeric($value) => $value + 0,
            default => $value,
        };
    }

    private function fileNames(string $type, array $files): mixed
    {
        $names = array_map(fn($file) => $file['name'], $files);

        return $type === 'anexo' ? ($names[0] ?? null) : $names;
    }

    // a escolha viaja pelo rótulo, e o complemento vai junto dele, numa string só
    private function choiceLabel(array $question, mixed $choice): ?string
    {
        $id = ResultValidator::choiceId($choice);

        foreach ($question['opcao']['itens'] ?? [] as $item) {
            if (($item['id'] ?? null) === $id) {
                $complement = is_array($choice) ? trim((string) ($choice['complemento'] ?? '')) : '';

                return $complement !== '' ? "{$item['label']}: {$complement}" : $item['label'];
            }
        }

        return null;
    }
}
