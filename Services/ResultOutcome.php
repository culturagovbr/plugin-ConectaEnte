<?php

namespace ConectaEnte\Services;

use MapasCulturais\i;

/**
 * O último desfecho do envio de resultado em texto para quem controla o edital, com as recusas nomeadas pela estrutura.
 */
final class ResultOutcome
{
    /**
     * @param array<string, string> $columnLabels o nome de cada coluna do modelo de planilha
     * @param array<int, string> $registrationNumbers o número que a grade mostra, por inscrição
     */
    public function __construct(private ?ResultForm $form = null, private array $columnLabels = [], private array $registrationNumbers = [])
    {
    }

    /**
     * @return array{status: string, message: string, items: string[], hiddenRows: int, refusedQuestions: object, refusedCells: object}
     */
    public function describe(array $lastSend): array
    {
        $status = (string) ($lastSend['status'] ?? '');
        $errors = is_array($lastSend['errors'] ?? null) ? $lastSend['errors'] : [];
        $reason = $lastSend['reason'] ?? null;

        $message = match ($status) {
            ResultSender::STATUS_SUCCESS => i::__('Resultado aceito pelo CultBR.'),
            ResultSender::STATUS_SIMULATED => i::__('Envio simulado: em modo de desenvolvimento nada foi enviado ao CultBR.'),
            ResultSender::STATUS_UNCERTAIN => i::__('O CultBR não respondeu ao envio. Ele pode ter chegado: confira no CultBR antes de enviar de novo.'),
            ResultSender::STATUS_REJECTED => $this->rejection((int) ($errors['status'] ?? 0), $errors, $reason),
            default => $reason ?: i::__('O envio não foi concluído.'),
        };

        $items = $status === ResultSender::STATUS_REJECTED ? [...$this->fieldItems($errors['fields'] ?? []), ...$this->rowItems($errors['rows'] ?? [])] : [];
        $totalRows = (int) ($errors['totalRows'] ?? 0);

        return [
            'status' => $status,
            'message' => $message,
            'items' => $items,
            'hiddenRows' => max(0, $totalRows - count($errors['rows'] ?? [])),
            'refusedQuestions' => (object) $this->refusedQuestions($status, $errors),
            'refusedCells' => (object) $this->refusedCells($status, $errors),
        ];
    }

    private function rejection(int $httpStatus, array $errors, ?string $reason): string
    {
        $detail = $errors['detail'] ?? null;

        return match (true) {
            $httpStatus === 400 => $this->badRequest((string) $detail),
            $httpStatus === 401 => i::__('O CultBR recusou o token do Ente Federado. Quem administra os Entes Federados precisa conferir o token.'),
            $httpStatus === 403 => i::__('O Ente Federado não tem permissão sobre este edital no CultBR.'),
            $httpStatus === 404 => i::__('O CultBR não encontrou este edital. Reenvie o edital antes do resultado.'),
            $httpStatus === 413 => i::__('O resultado passou do tamanho que o CultBR aceita.'),
            $httpStatus === 422 && !empty($errors['rows']) => i::__('O CultBR recusou linhas da planilha de contemplados. Nenhuma linha foi gravada.'),
            $httpStatus === 422 && !empty($errors['fields']) => i::__('O CultBR recusou respostas do formulário.'),
            default => $detail ?: ($reason ?: sprintf(i::__('O CultBR recusou o resultado (HTTP %d).'), $httpStatus)),
        };
    }

    // as três causas do contrato chegam no `detail`; só a do tipo de ação tem frase fixa
    private function badRequest(string $detail): string
    {
        // o nome do arquivo recusado vem entre aspas e não diz a causa
        $lower = mb_strtolower((string) preg_replace("/'.*'/s", '', $detail));

        return match (true) {
            str_contains($lower, 'não permite resultados') => i::__('O CultBR não aceita resultado para a ação deste edital.'),
            str_contains($lower, 'questão') || str_contains($lower, 'questao') => i::__('O formulário de resultado mudou no CultBR. Recarregue a aba, confira as respostas e envie de novo.'),
            str_contains($lower, 'ação') || str_contains($lower, 'acao') => i::__('O edital está sem ação associada no CultBR. Reenvie o edital antes do resultado.'),
            default => $detail !== '' ? $detail : i::__('O CultBR recusou o pedido de envio.'),
        };
    }

    private function fieldItems(array $fields): array
    {
        return array_map(function ($field) {
            $question = isset($field['questionId']) ? $this->form?->question((int) $field['questionId']) : null;
            $name = $question ? ($question['config']['textoPergunta'] ?? $question['nome'] ?? $field['field']) : $field['field'];

            return $this->labelled($name, (string) $field['message']);
        }, $fields);
    }

    // a pergunta que já termina em pontuação não leva dois-pontos
    private function labelled(string $name, string $message): string
    {
        $separator = preg_match('/[?!.:]$/u', trim($name)) ? ' ' : ': ';

        return trim($name) . $separator . $message;
    }

    /** @return array<int, string> o motivo por questão, enquanto a resposta recusada não é alterada */
    private function refusedQuestions(string $status, array $errors): array
    {
        return array_column($this->standing($status, $errors['fields'] ?? [], 'questionId'), 'message', 'questionId');
    }

    /** @return array<string, string> o motivo por célula, em `inscrição:coluna` */
    private function refusedCells(string $status, array $errors): array
    {
        $cells = [];

        foreach ($this->standing($status, $errors['rows'] ?? [], 'registrationId') as $row) {
            if (isset($row['coluna'])) {
                $cells["{$row['registrationId']}:{$row['coluna']}"] = (string) ($row['descricao'] ?? $row['regra'] ?? '');
            }
        }

        return $cells;
    }

    private function standing(string $status, array $refusals, string $key): array
    {
        return $status === ResultSender::STATUS_REJECTED
            ? array_filter($refusals, fn($refusal) => isset($refusal[$key]) && empty($refusal['edited']))
            : [];
    }

    private function rowItems(array $rows): array
    {
        return array_map(function ($row) {
            $column = $row['coluna'] ?? null;
            $columnName = $column === null ? i::__('linha inteira') : ($this->columnLabels[$column] ?? $column);
            $registration = isset($row['registrationId']) ? sprintf(i::__(' (inscrição %s)'), $this->registrationNumbers[$row['registrationId']] ?? $row['registrationId']) : '';

            return sprintf(i::__('Linha %d%s, %s: %s'), (int) ($row['linha'] ?? 0), $registration, $columnName, $row['descricao'] ?? $row['regra'] ?? '');
        }, $rows);
    }
}
