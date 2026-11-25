<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\ResultCell;
use DateTime;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Registration;
use MapasCulturais\Entities\RegistrationMeta;
use MapasCulturais\i;

/**
 * A grade de contemplados: uma linha por inscrição aprovada na fase final, com a célula do gestor sobre a da ficha e a calculada.
 */
final class ResultRows
{
    const DEFAULT_PAGE_SIZE = 50;
    const BATCH_SIZE = 200;
    const PROPONENT_TYPE_PENDING = 'tipo_proponente';

    public function __construct(private ResultSpreadsheet $spreadsheet)
    {
    }

    /**
     * Uma página da grade, com o total de linhas e o de linhas com pendência.
     */
    public function page(Opportunity $opportunity, int $page = 1, int $pageSize = self::DEFAULT_PAGE_SIZE): array
    {
        $context = $this->context($opportunity);
        $page = max(1, $page);
        $registrations = $this->awarded($opportunity, $pageSize, ($page - 1) * $pageSize);

        return [
            'total' => $this->awardedCount($opportunity),
            'page' => $page,
            'pageSize' => $pageSize,
            'columns' => $context['columns'],
            'rows' => $this->build($opportunity, $registrations, $context),
            'pendingRows' => $this->pendingRowCount($opportunity, $context),
        ];
    }

    /** Uma página de linhas, sem o resumo da grade. */
    public function rows(Opportunity $opportunity, int $page, int $pageSize): array
    {
        return $this->build($opportunity, $this->awarded($opportunity, $pageSize, (max(1, $page) - 1) * $pageSize), $this->context($opportunity));
    }

    /** Quantas linhas a grade tem: as aprovadas na fase final, ou nenhuma sem planilha. */
    public function awardedTotal(Opportunity $opportunity): int
    {
        return $this->context($opportunity)['hasSpreadsheet'] ? $this->awardedCount($opportunity) : 0;
    }

    /** Quantas linhas ainda têm pendência, calculado em lotes. */
    public function pendingRowCount(Opportunity $opportunity, ?array $context = null): int
    {
        $context ??= $this->context($opportunity);
        $pending = 0;

        foreach ($this->batches($opportunity) as $registrations) {
            foreach ($this->build($opportunity, $registrations, $context) as $row) {
                $pending += $row['pending'] ? 1 : 0;
            }
        }

        return $pending;
    }

    /** @return list<array<string, string>>|null as linhas prontas para o envio, ou null se alguma tem pendência */
    public function completeRows(Opportunity $opportunity): ?array
    {
        $rows = $this->completeRowsByRegistration($opportunity);

        return $rows === null ? null : array_values($rows);
    }

    /** @return array<int, array<string, string>>|null as mesmas linhas, na mesma ordem, pelo id da inscrição */
    public function completeRowsByRegistration(Opportunity $opportunity): ?array
    {
        $context = $this->context($opportunity);
        $rows = [];

        foreach ($this->batches($opportunity) as $registrations) {
            foreach ($this->build($opportunity, $registrations, $context) as $row) {
                if ($row['pending']) {
                    return null;
                }

                $rows[$row['registrationId']] = array_map(fn($cell) => (string) ($cell['value'] ?? ''), $row['cells']);
            }
        }

        return $rows;
    }

    /**
     * Grava a célula do gestor; null devolve a célula à ficha, e texto vazio é a decisão de deixá-la em branco.
     */
    public function saveCell(Opportunity $opportunity, int $registrationId, string $columnKey, mixed $value): ?string
    {
        $column = array_column($this->context($opportunity)['columns'], null, 'coluna')[$columnKey] ?? null;
        $registration = App::i()->repo(Registration::class)->find($registrationId);

        $error = match (true) {
            $column === null => i::__('Esta coluna não existe no modelo de planilha do edital.'),
            !$registration || $registration->opportunity->id !== $opportunity->lastPhase?->id => i::__('Esta inscrição não é contemplada neste edital.'),
            $value !== null && !is_scalar($value) => i::__('Cada coluna aceita um único valor.'),
            default => $this->cellError($column, $value === null ? '' : trim((string) $value)),
        };

        if ($error === null) {
            $this->store($opportunity, $registrationId, $columnKey, $value === null ? null : trim((string) $value));
        }

        return $error;
    }

    private function context(Opportunity $opportunity): array
    {
        $model = $this->spreadsheet->model($opportunity)->body ?? [];

        return [
            'hasSpreadsheet' => !empty($model['possui_planilha']),
            'columns' => array_values((array) ($model['campos'] ?? [])),
            'mapping' => $this->spreadsheet->mapping($opportunity),
            'ranges' => $this->rangeValues($opportunity),
        ];
    }

    private function build(Opportunity $opportunity, array $registrations, array $context): array
    {
        if (!$registrations || !$context['hasSpreadsheet']) {
            return [];
        }

        $manager = $this->managerCells($registrations);
        $ficha = $this->fichaValues($opportunity, $registrations, $context['mapping']);
        $rows = [];

        foreach ($registrations as $registration) {
            $cells = [];

            foreach ($context['columns'] as $column) {
                $cells[$column['coluna']] = $this->cell($column, $registration, $context, $manager[$registration->id] ?? [], $ficha[$registration->number] ?? []);
            }

            $rows[] = [
                'registrationId' => (int) $registration->id,
                'number' => (string) $registration->number,
                'name' => (string) $registration->owner?->name,
                'proponentType' => $registration->proponentType,
                'range' => $registration->range,
                'cells' => $cells,
                'pending' => $this->pending($context['columns'], $cells, $registration),
            ];
        }

        return $rows;
    }

    private function cell(array $column, Registration $registration, array $context, array $manager, array $ficha): array
    {
        $key = $column['coluna'];

        if (array_key_exists($key, $manager)) {
            return ['value' => $manager[$key], 'source' => 'manager'];
        }

        $value = $this->fichaValue($column, $registration, $context['mapping'], $ficha) ?? $this->automaticValue($column, $registration, $context['ranges']);

        if ($value === null || $value === '') {
            return ['value' => null, 'source' => 'empty'];
        }

        return ['value' => $this->translated($column, (string) $value, $context['mapping']), 'source' => $this->isMapped($column, $context['mapping'], $registration) ? 'ficha' : 'auto'];
    }

    private function fichaValue(array $column, Registration $registration, array $mapping, array $ficha): ?string
    {
        $fieldId = $this->sourceField($column, $mapping, $registration);

        return $fieldId && isset($ficha[$fieldId]) ? (string) $ficha[$fieldId] : null;
    }

    // o documento vem do de/para por tipo de proponente; as demais colunas, do de/para da coluna
    private function sourceField(array $column, array $mapping, Registration $registration): ?int
    {
        $kind = $column['tipo'] ?? null;

        if (in_array($kind, ResultSpreadsheet::DOCUMENT_KINDS, true)) {
            return $mapping['documents'][$registration->proponentType][$kind] ?? $mapping['columns'][$column['coluna']] ?? null;
        }

        return $mapping['columns'][$column['coluna']] ?? null;
    }

    private function isMapped(array $column, array $mapping, Registration $registration): bool
    {
        return $this->sourceField($column, $mapping, $registration) !== null;
    }

    private function automaticValue(array $column, Registration $registration, array $ranges): ?string
    {
        $key = $column['coluna'];

        return match (true) {
            ResultSpreadsheet::isAgentTypeColumn($column) => $registration->proponentType ?: null,
            ($column['tipo'] ?? null) === 'monetario' && isset($ranges[$registration->range]) => number_format($ranges[$registration->range], 2, '.', ''),
            str_starts_with($key, 'nome') => $registration->owner?->name,
            default => null,
        };
    }

    private function translated(array $column, string $value, array $mapping): string
    {
        if (($column['tipo'] ?? null) !== 'lista' || in_array($value, (array) ($column['opcoes'] ?? []), true)) {
            return $value;
        }

        return $mapping['equivalences'][$column['coluna']][$value] ?? $value;
    }

    private function pending(array $columns, array $cells, Registration $registration): array
    {
        $pending = $registration->proponentType ? [] : [self::PROPONENT_TYPE_PENDING];

        foreach ($columns as $column) {
            $value = $cells[$column['coluna']]['value'] ?? null;
            $required = ($column['obrigatoriedade'] ?? null) === 'obrigatorio';

            if (($required && ($value === null || $value === '')) || ($value !== null && $value !== '' && $this->cellError($column, (string) $value))) {
                $pending[] = $column['coluna'];
            }
        }

        return $pending;
    }

    /** O que a edição quebra; condicional e obrigatoriedade ficam para a pendência e para o 422 do CultBR. */
    private function cellError(array $column, string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        return match ($column['tipo'] ?? null) {
            'cpf' => ResultValidator::isValidCpf($value) ? null : i::__('CPF inválido.'),
            'cnpj' => ResultValidator::isValidCnpj($value) ? null : i::__('CNPJ inválido.'),
            'cep' => preg_match('/^\d{8}$/', preg_replace('/\D/', '', $value)) ? null : i::__('Informe um CEP com 8 dígitos.'),
            'monetario' => is_numeric($value) && $value >= 0 ? null : i::__('Informe um valor em reais, como 15000.00.'),
            'lista' => in_array($value, (array) ($column['opcoes'] ?? []), true) ? null : i::__('Escolha uma das opções do CultBR.'),
            default => null,
        };
    }

    private function store(Opportunity $opportunity, int $registrationId, string $columnKey, ?string $value): void
    {
        $em = App::i()->em;
        $cell = App::i()->repo(ResultCell::class)->findOneBy(['registrationId' => $registrationId, 'columnKey' => $columnKey]);

        // a entidade é fechada para o genérico: grava pelo EntityManager, como o histórico de envio
        if ($value === null) {
            if ($cell) {
                $em->remove($cell);
                $em->flush();
            }

            return;
        }

        $cell ??= new ResultCell;
        $cell->opportunityId = (int) $opportunity->id;
        $cell->registrationId = $registrationId;
        $cell->columnKey = $columnKey;
        $cell->value = $value;
        $cell->updateTimestamp = new DateTime;

        $em->persist($cell);
        $em->flush();
    }

    /** @return array<int, array<string, ?string>> por inscrição */
    private function managerCells(array $registrations): array
    {
        $cells = [];

        foreach (App::i()->repo(ResultCell::class)->findBy(['registrationId' => array_map(fn($registration) => $registration->id, $registrations)]) as $cell) {
            $cells[$cell->registrationId][$cell->columnKey] = $cell->value;
        }

        return $cells;
    }

    /** @return array<string, array<int, mixed>> por número da inscrição, o valor de cada campo mapeado, lido na inscrição da fase do campo */
    private function fichaValues(Opportunity $opportunity, array $registrations, array $mapping): array
    {
        $fieldIds = array_unique(array_filter(array_merge(array_values($mapping['columns']), ...array_map('array_values', array_values($mapping['documents'])))));
        $phaseOf = array_column($this->spreadsheet->fields($opportunity), 'phaseId', 'id');
        $numbers = array_map(fn($registration) => $registration->number, $registrations);
        $values = [];

        foreach (array_unique(array_intersect_key($phaseOf, array_flip($fieldIds))) as $phaseId) {
            $phaseRegistrations = App::i()->repo(Registration::class)->findBy(['opportunity' => $phaseId, 'number' => $numbers]);
            $keys = array_map(fn($id) => "field_{$id}", array_keys(array_filter($phaseOf, fn($phase) => $phase === $phaseId)));

            foreach ($phaseRegistrations ? App::i()->repo(RegistrationMeta::class)->findBy(['owner' => $phaseRegistrations, 'key' => $keys]) : [] as $meta) {
                $values[$meta->owner->number][(int) substr($meta->key, 6)] = is_scalar($meta->value) ? $meta->value : null;
            }
        }

        return $values;
    }

    /** @return array<string, float> por rótulo da faixa, o valor de cada vaga */
    private function rangeValues(Opportunity $opportunity): array
    {
        $values = [];

        foreach ((array) $opportunity->registrationRanges as $range) {
            $limit = (int) ($range['limit'] ?? 0);

            if (isset($range['label']) && is_numeric($range['value'] ?? null) && $limit > 0) {
                $values[$range['label']] = round((float) $range['value'] / $limit, 2);
            }
        }

        return $values;
    }

    private function awarded(Opportunity $opportunity, int $limit, int $offset): array
    {
        $finalPhase = $opportunity->lastPhase;

        return $finalPhase ? App::i()->repo(Registration::class)->findBy(['opportunity' => $finalPhase, 'status' => Registration::STATUS_APPROVED], ['id' => 'ASC'], $limit, $offset) : [];
    }

    private function awardedCount(Opportunity $opportunity): int
    {
        $finalPhase = $opportunity->lastPhase;

        return $finalPhase ? (int) App::i()->repo(Registration::class)->count(['opportunity' => $finalPhase, 'status' => Registration::STATUS_APPROVED]) : 0;
    }

    private function batches(Opportunity $opportunity): \Generator
    {
        for ($offset = 0; $batch = $this->awarded($opportunity, self::BATCH_SIZE, $offset); $offset += self::BATCH_SIZE) {
            yield $batch;
        }
    }
}
