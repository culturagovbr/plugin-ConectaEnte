<?php

namespace ConectaEnte\Services;

use MapasCulturais\App;
use MapasCulturais\Entities\AgentMeta;
use MapasCulturais\Entities\EvaluationMethodConfigurationAgentRelation as ValuerRelation;
use MapasCulturais\Entities\Opportunity;

/**
 * A comissão avaliadora do edital na questão do resultado: a linha guarda o agente, e o CPF dele não sai do servidor.
 */
final class ResultCommittee
{
    const VALUER_STATUSES = [ValuerRelation::STATUS_ACTIVE, ValuerRelation::STATUS_SENT];
    const CELL_MAX_LENGTH = 120;

    /**
     * As linhas com que a questão abre quando ainda não tem resposta.
     *
     * @return array<int, list<array>> por questão
     */
    public function suggested(Opportunity $opportunity, ResultForm $form, array $answers): array
    {
        $unanswered = array_filter($form->committeeIds(), fn($id) => ResultValidator::isEmpty($answers[$id] ?? null));
        $rows = $unanswered ? $this->rows($opportunity) : [];

        return $rows ? $this->forScreen(array_fill_keys($unanswered, $rows), $form) : [];
    }

    /** As respostas com o CPF do avaliador mascarado. */
    public function forScreen(array $answers, ResultForm $form): array
    {
        return $this->withDocuments($answers, $form, fn($row, $cpf) => ['cpf' => $this->masked($cpf)] + $row);
    }

    /** As respostas como vão ao CultBR: com o CPF e sem o agente. */
    public function complete(array $answers, ResultForm $form): array
    {
        $answers = $this->withDocuments($answers, $form, fn($row, $cpf) => ['cpf' => $this->formatted($cpf)] + $row);

        return $this->mapRows($answers, $form, fn($row) => array_diff_key($row, ['agentId' => null]));
    }

    /** As linhas recebidas da tela, só com agente que é da comissão ou que já estava gravado, e sem o CPF do cadastro. */
    public function toStore(Opportunity $opportunity, ResultForm $form, array $answers, array $saved): array
    {
        $received = $this->agentIds($answers, $form);
        $allowed = $received ? array_merge(array_keys($this->members($opportunity)), $this->agentIds($saved, $form)) : [];
        $answers = $this->mapRows($answers, $form, fn($row) => in_array((int) ($row['agentId'] ?? 0), $allowed, true) ? $row : array_diff_key($row, ['agentId' => null]));

        return $this->withDocuments($answers, $form, fn($row) => array_diff_key($row, ['cpf' => null]));
    }

    private function rows(Opportunity $opportunity): array
    {
        $rows = [];

        foreach ($this->members($opportunity) as $agentId => $member) {
            $rows[] = [
                'agentId' => $agentId,
                'nome' => mb_substr($member['name'], 0, self::CELL_MAX_LENGTH),
                'cpf' => '',
                'vinculo' => '',
                'categoria_grupo' => mb_substr(implode(', ', $member['groups']), 0, self::CELL_MAX_LENGTH),
            ];
        }

        return $rows;
    }

    /** @return array<int, array{name: string, groups: string[]}> por agente */
    private function members(Opportunity $opportunity): array
    {
        $members = [];

        foreach ($opportunity->allPhases as $phase) {
            if (!$phase->evaluationMethodConfiguration) {
                continue;
            }

            foreach ($phase->evaluationMethodConfiguration->getCommittee() as $relation) {
                if (!in_array((int) $relation->status, self::VALUER_STATUSES, true)) {
                    continue;
                }

                $members[$relation->agent->id] ??= ['name' => (string) $relation->agent->name, 'groups' => []];
                $members[$relation->agent->id]['groups'][$relation->group] = $relation->group;
            }
        }

        return $members;
    }

    private function withDocuments(array $answers, ResultForm $form, callable $apply): array
    {
        $documents = $this->documentsOf($this->agentIds($answers, $form));

        return $this->mapRows($answers, $form, fn($row) => isset($documents[$row['agentId'] ?? 0]) ? $apply($row, $documents[$row['agentId']]) : $row);
    }

    // pela Entity do metadado: a propriedade do agente some para quem não controla o cadastro dele
    private function documentsOf(array $agentIds): array
    {
        $documents = [];

        foreach ($agentIds ? App::i()->repo(AgentMeta::class)->findBy(['owner' => $agentIds, 'key' => 'cpf']) : [] as $meta) {
            if (ResultValidator::isValidCpf((string) $meta->value)) {
                $documents[$meta->owner->id] = preg_replace('/\D/', '', $meta->value);
            }
        }

        return $documents;
    }

    /** @return int[] */
    private function agentIds(array $answers, ResultForm $form): array
    {
        $ids = [];

        $this->mapRows($answers, $form, function ($row) use (&$ids) {
            $ids[] = (int) ($row['agentId'] ?? 0);

            return $row;
        });

        return array_values(array_unique(array_filter($ids)));
    }

    private function mapRows(array $answers, ResultForm $form, callable $map): array
    {
        foreach ($form->committeeIds() as $id) {
            if (is_array($answers[$id] ?? null)) {
                $answers[$id] = array_map(fn($row) => is_array($row) ? $map($row) : $row, $answers[$id]);
            }
        }

        return $answers;
    }

    private function formatted(string $cpf): string
    {
        return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $cpf);
    }

    private function masked(string $cpf): string
    {
        return preg_replace('/^\d{3}(\d{3})(\d{3})\d{2}$/', '***.$1.$2-**', $cpf);
    }
}
