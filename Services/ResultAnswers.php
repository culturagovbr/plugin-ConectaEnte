<?php

namespace ConectaEnte\Services;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Payload\OpportunityPayload;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Registration;
use MapasCulturais\i;

/**
 * As respostas do formulário de resultado: o que o Mapas calcula, o que sugere e o que o gestor gravou.
 */
final class ResultAnswers
{
    const ENROLLED_STATUSES = [
        Registration::STATUS_SENT,
        Registration::STATUS_INVALID,
        Registration::STATUS_NOTAPPROVED,
        Registration::STATUS_WAITLIST,
        Registration::STATUS_APPROVED,
    ];

    /**
     * As calculadas e só leitura, por questão; a que não tem fonte fica de fora e volta ao gestor.
     *
     * @return array<int, mixed>
     */
    public function computed(Opportunity $opportunity, ResultForm $form): array
    {
        $values = [];

        foreach ($form->questions() as $id => $question) {
            if ($form->isAutomatic($question)) {
                $value = $this->automaticValue($opportunity, $form, $question);

                if ($value !== null) {
                    $values[$id] = $value;
                }
            }
        }

        return $values;
    }

    /** @return array<int, float> as sugestões editáveis, hoje só o valor repassado */
    public function suggested(Opportunity $opportunity, ResultForm $form): array
    {
        $transferred = $this->transferredSuggestion($opportunity);
        $values = [];

        foreach ($form->questions() as $id => $question) {
            if ($form->isSuggested($question) && $transferred !== null) {
                $values[$id] = $transferred;
            }
        }

        return $values;
    }

    /** @return array<int, string> o que as questões espelho mostram do edital; não vai no envio */
    public function mirrored(Opportunity $opportunity, ResultForm $form): array
    {
        $values = [];

        foreach ($form->questions() as $id => $question) {
            $values[$id] = match ($form->mirrorOf($question)) {
                ResultForm::MIRRORED_TITLE => $opportunity->name ?: null,
                ResultForm::MIRRORED_DETAIL => OpportunityPayload::objectDetail($opportunity),
                default => null,
            };
        }

        return array_filter($values);
    }

    /** @return array{versionId: ?int, answers: array<int, mixed>} */
    public function saved(Opportunity $opportunity): array
    {
        // só a propriedade passa pelo tipo json, e ele decodifica como objeto
        $stored = json_decode(json_encode($opportunity->{CultBrMetadata::RESULT_FORM}), true);
        $stored = is_array($stored) ? $stored : [];

        return [
            'versionId' => isset($stored['versionId']) ? (int) $stored['versionId'] : null,
            'answers' => (array) ($stored['answers'] ?? []),
        ];
    }

    /**
     * Grava as respostas válidas do formulário inteiro e devolve o erro de cada inválida, sem derrubar as outras.
     *
     * @return array<int, string> erro por questão
     */
    public function save(Opportunity $opportunity, ResultForm $form, array $answers): array
    {
        $computed = $this->computed($opportunity, $form);
        $kept = array_intersect_key($this->saved($opportunity)['answers'], $form->questions());
        $errors = [];

        $validator = new ResultValidator($form);

        foreach ($answers as $id => $value) {
            // o anexo muda só pela rota de arquivo: daqui não se aponta para um arquivo qualquer
            if (in_array($form->question((int) $id)['tipo_questao'] ?? null, ResultValidator::ATTACHMENT_TYPES, true)) {
                continue;
            }

            $error = $this->refusal($form, $computed, (int) $id) ?? $validator->error($form->question((int) $id), $value);

            if ($error) {
                $errors[(int) $id] = $error;
            } else {
                $kept[(int) $id] = $value;
            }
        }

        // a atribuição passa pelo serializador do tipo json; setMetadata gravaria o array cru
        $opportunity->{CultBrMetadata::RESULT_FORM} = ['versionId' => $form->versionId(), 'answers' => $kept];
        // saveMetadata, nunca save: salvar a oportunidade selada a reenviaria
        $opportunity->saveMetadata();

        return $errors;
    }

    /** @return int[] obrigatórias visíveis e editáveis ainda sem resposta */
    public function missing(Opportunity $opportunity, ResultForm $form): array
    {
        return (new ResultValidator($form))->missing($this->saved($opportunity)['answers'], $this->computed($opportunity, $form));
    }

    /** Se as respostas gravadas são de outra versão do formulário. */
    public function isStale(Opportunity $opportunity, ResultForm $form): bool
    {
        $versionId = $this->saved($opportunity)['versionId'];

        return $versionId !== null && $versionId !== $form->versionId();
    }

    private function refusal(ResultForm $form, array $computed, int $id): ?string
    {
        $question = $form->question($id);

        return match (true) {
            $question === null => i::__('Esta questão não existe nesta versão do formulário.'),
            $form->isReadOnly($question) || array_key_exists($id, $computed) => i::__('Esta questão é calculada pelo Mapas.'),
            default => null,
        };
    }

    private function automaticValue(Opportunity $opportunity, ResultForm $form, array $question): mixed
    {
        return match (true) {
            $form->hasTag($question, ResultForm::ENROLLED_PREFIX) => $this->countRegistrations($opportunity, self::ENROLLED_STATUSES),
            $form->hasAnyTag($question, ResultForm::AWARDED_PREFIXES) => $this->awardedCount($opportunity),
            default => $this->categoryRows($opportunity, $form, $question),
        };
    }

    private function countRegistrations(Opportunity $opportunity, array $statuses, array $criteria = []): int
    {
        return (int) App::i()->repo(Registration::class)->count(['opportunity' => $opportunity, 'status' => $statuses] + $criteria);
    }

    private function awardedCount(Opportunity $opportunity, array $criteria = []): int
    {
        $finalPhase = $opportunity->lastPhase;

        return $finalPhase?->isLastPhase ? $this->countRegistrations($finalPhase, [Registration::STATUS_APPROVED], $criteria) : 0;
    }

    /** @return list<array{label: string, awarded: int, perVacancy: ?float}>|null */
    private function ranges(Opportunity $opportunity): ?array
    {
        $rows = [];

        foreach ((array) $opportunity->registrationRanges as $range) {
            $label = trim((string) ($range['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $limit = (int) ($range['limit'] ?? 0);
            // o valor da faixa é o total dela: é o que a regra de publicação soma contra o Valor total
            $perVacancy = is_numeric($range['value'] ?? null) && $limit > 0 ? round((float) $range['value'] / $limit, 2) : null;

            $rows[] = ['label' => $label, 'awarded' => $this->awardedCount($opportunity, ['range' => $label]), 'perVacancy' => $perVacancy];
        }

        return $rows ?: null;
    }

    private function transferredSuggestion(Opportunity $opportunity): ?float
    {
        $total = 0.0;

        foreach ($this->ranges($opportunity) ?? [] as $range) {
            if ($range['perVacancy'] === null) {
                return null;
            }

            $total += $range['perVacancy'] * $range['awarded'];
        }

        return $this->ranges($opportunity) ? round($total, 2) : null;
    }

    private function categoryRows(Opportunity $opportunity, ResultForm $form, array $question): ?array
    {
        $columns = $this->columnKeys($question);
        $ranges = $this->ranges($opportunity);

        if (!$columns) {
            return null;
        }

        if ($ranges) {
            return array_map(fn($range) => $this->row($columns, $range['label'], $range['awarded'], $range['perVacancy']), $ranges);
        }

        return ($question['tag'] ?? null) === ResultForm::SINGLE_ROW_CATEGORY_TAG ? $this->singleRow($opportunity, $form, $columns) : null;
    }

    private function singleRow(Opportunity $opportunity, ResultForm $form, array $columns): ?array
    {
        $awarded = $this->awardedCount($opportunity);
        $transferred = $this->transferredAnswer($opportunity, $form);

        if ($awarded === 0 || $transferred === null) {
            return null;
        }

        return [$this->row($columns, (string) $opportunity->name, $awarded, round($transferred / $awarded, 2))];
    }

    private function transferredAnswer(Opportunity $opportunity, ResultForm $form): ?float
    {
        $answers = $this->saved($opportunity)['answers'];

        foreach ($form->questions() as $id => $question) {
            if ($form->isSuggested($question) && is_numeric($answers[$id] ?? null)) {
                return (float) $answers[$id];
            }
        }

        return null;
    }

    private function row(array $columns, string $label, int $awarded, ?float $perVacancy): array
    {
        $row = [];

        foreach ($columns as $key) {
            $row[$key] = match (true) {
                str_contains($key, 'valor_total') => $perVacancy === null ? null : round($perVacancy * $awarded, 2),
                str_contains($key, 'valor') => $perVacancy,
                str_contains($key, 'quantidade') => $awarded,
                str_contains($key, 'categoria') => $label,
                default => null,
            };
        }

        return $row;
    }

    // as duas formas de tabela: `tabela` nomeia as colunas por `id`, `Tabela Dinâmica` por `key`
    private function columnKeys(array $question): array
    {
        $config = $question['config'] ?? [];
        $columns = $config['tabela']['colunas'] ?? $config['columns'] ?? [];

        return array_values(array_filter(array_map(fn($column) => $column['id'] ?? $column['key'] ?? null, $columns)));
    }
}
