<?php

namespace ConectaEnte\Services;

use ConectaEnte\Http\ApiRead;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use MapasCulturais\App;
use MapasCulturais\Entities\Agent;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\RegistrationFieldConfiguration;
use MapasCulturais\Entities\RegistrationMeta;
use MapasCulturais\Entities\Space;
use MapasCulturais\i;

/**
 * A planilha de contemplados: o modelo que o CultBR devolve e o de/para dos campos da ficha para as colunas dele.
 */
final class ResultSpreadsheet
{
    const MODEL_CACHE_SECONDS = 300;
    const DOCUMENT_KINDS = ['cpf', 'cnpj'];
    // tipos de campo que guardam mais de um valor: nenhum deles cabe numa célula
    const MULTI_VALUE_FIELD_TYPES = ['checkboxes', 'multiselect', 'checklist', 'file', 'persons', 'links', 'section', self::COMPOSITE];
    const COMPOSITE = 'composite';
    const ENTITY_FIELDS = ['agent-owner-field' => [Agent::class, 1], 'agent-collective-field' => [Agent::class, 2], 'space-field' => [Space::class, null]];
    const FIELD_TYPES_BY_COLUMN = [
        'cpf' => ['cpf', 'text'],
        'cnpj' => ['cnpj', 'text'],
        'cep' => ['cep', 'text', 'addresses'],
        'monetario' => ['currency', 'number', 'text'],
        'lista' => ['select', 'radio', 'text'],
    ];

    public function __construct(private Plugin $plugin)
    {
    }

    /** O modelo de planilha do edital, lido no CultBR com cache curto. */
    public function model(Opportunity $opportunity): ApiRead
    {
        $parEditalId = (int) $opportunity->getMetadata(CultBrMetadata::PAR_EDITAL_ID);
        $key = "conectaente:result-spreadsheet:{$parEditalId}";
        $cached = App::i()->mscache->fetch($key);

        if (is_array($cached)) {
            return ApiRead::ok($cached);
        }

        $token = $this->plugin->sealedOpportunity()->federativeEntityOf($opportunity)?->token;
        $read = $this->plugin->client()->getResultSpreadsheetModel((string) $token, $parEditalId);

        if ($read->body !== null) {
            App::i()->mscache->save($key, $read->body, self::MODEL_CACHE_SECONDS);
        }

        return $read;
    }

    /** A coluna que, sem campo ligado, vem do tipo de proponente da inscrição. */
    public static function isAgentTypeColumn(array $column): bool
    {
        return str_contains((string) ($column['coluna'] ?? ''), 'tipo_agente');
    }

    /** Se o edital leva planilha de contemplados no resultado. */
    public function isRequired(Opportunity $opportunity): bool
    {
        return !empty($this->model($opportunity)->body['possui_planilha']);
    }

    /**
     * O que a tela do de/para precisa: modelo, campos da ficha, o que foi salvo e a sugestão.
     */
    public function mappingView(Opportunity $opportunity): array
    {
        $read = $this->model($opportunity);
        $model = $read->body ?? [];
        $columns = (array) ($model['campos'] ?? []);
        $fields = $this->fields($opportunity);
        $mapping = $this->mapping($opportunity);

        return [
            'available' => $read->body !== null,
            'hasSpreadsheet' => !empty($model['possui_planilha']),
            'reason' => $model['motivo'] ?? $read->message,
            'model' => $model['nome_modelo'] ?? $model['modelo'] ?? null,
            'columns' => $columns,
            'fields' => $fields,
            'proponentTypes' => $this->proponentTypes(),
            'mapping' => $mapping,
            'suggestion' => (object) $this->suggestion($columns, $fields),
            'pendingEquivalences' => (object) $this->pendingEquivalences($opportunity, $columns, $mapping),
        ];
    }

    /**
     * Grava o de/para validado contra os campos do próprio edital; devolve o erro de cada escolha recusada.
     *
     * @return array<string, string>
     */
    public function save(Opportunity $opportunity, array $input): array
    {
        $model = $this->model($opportunity)->body ?? [];
        $columns = array_column((array) ($model['campos'] ?? []), null, 'coluna');
        $fields = array_column($this->fields($opportunity), null, 'id');
        $mapping = $this->mapping($opportunity);
        $errors = [];

        foreach ((array) ($input['columns'] ?? []) as $column => $fieldId) {
            $error = $this->columnError($columns[$column] ?? null, $fields[(int) $fieldId] ?? null, $fieldId);

            if ($error) {
                $errors["columns.{$column}"] = $error;
            } else {
                $mapping['columns'][$column] = $fieldId === null ? null : (int) $fieldId;
            }
        }

        foreach ((array) ($input['documents'] ?? []) as $type => $kinds) {
            foreach (self::DOCUMENT_KINDS as $kind) {
                if (!array_key_exists($kind, (array) $kinds)) {
                    continue;
                }

                $fieldId = $kinds[$kind];
                $error = $this->documentError($type, $kind, $fields[(int) $fieldId] ?? null, $fieldId);

                if ($error) {
                    $errors["documents.{$type}.{$kind}"] = $error;
                } else {
                    $mapping['documents'][$type][$kind] = $fieldId === null ? null : (int) $fieldId;
                }
            }
        }

        foreach ((array) ($input['equivalences'] ?? []) as $column => $pairs) {
            foreach ((array) $pairs as $value => $option) {
                if (in_array($option, (array) ($columns[$column]['opcoes'] ?? []), true)) {
                    $mapping['equivalences'][$column][$value] = $option;
                } else {
                    $errors["equivalences.{$column}"] = i::__('Escolha uma das opções do CultBR.');
                }
            }
        }

        if (!empty($input['confirmSuggestion'])) {
            $mapping['columns'] = array_filter($mapping['columns'], fn($id) => $id !== null) + $this->suggestion(array_values($columns), array_values($fields));
            $mapping['confirmed'] = true;
        }

        // a atribuição passa pelo serializador do tipo json; setMetadata gravaria o array cru
        $opportunity->{CultBrMetadata::RESULT_MAPPING} = $mapping;
        $opportunity->saveMetadata();

        return $errors;
    }

    /** @return array{columns: array, documents: array, equivalences: array, confirmed: bool} */
    public function mapping(Opportunity $opportunity): array
    {
        // só a propriedade passa pelo tipo json, e ele decodifica como objeto
        $stored = json_decode(json_encode($opportunity->{CultBrMetadata::RESULT_MAPPING}), true) ?: [];
        $documents = [];

        foreach ($this->proponentTypes() as $type) {
            $documents[$type] = array_merge(array_fill_keys(self::DOCUMENT_KINDS, null), (array) ($stored['documents'][$type] ?? []));
        }

        return [
            'columns' => (array) ($stored['columns'] ?? []),
            'documents' => $documents,
            'equivalences' => (array) ($stored['equivalences'] ?? []),
            'confirmed' => !empty($stored['confirmed']),
        ];
    }

    /** @return list<array{id: int, title: string, fieldType: string, phase: string, step: string}> os campos da ficha de todas as fases */
    public function fields(Opportunity $opportunity): array
    {
        $fields = [];

        foreach ($opportunity->allPhases as $phase) {
            foreach ($phase->registrationFieldConfigurations as $field) {
                if ($field->fieldType === 'section') {
                    continue;
                }

                $fields[] = [
                    'id' => (int) $field->id,
                    'title' => (string) $field->title,
                    'fieldType' => $this->typeOf($field),
                    'phase' => (string) $phase->name,
                    'phaseId' => (int) $phase->id,
                    'step' => (string) ($field->step?->name ?? ''),
                ];
            }
        }

        return $fields;
    }

    /** @return string[] todos os tipos da instalação: a inscrição pode trazer um que o edital já não aceita */
    public function proponentTypes(): array
    {
        return array_values(array_map('strval', App::i()->config['registration.proponentTypes'] ?? []));
    }

    // o campo de agente ou espaço vale pelo dado que traz
    private function typeOf(RegistrationFieldConfiguration $field): string
    {
        if (!isset(self::ENTITY_FIELDS[$field->fieldType])) {
            return (string) $field->fieldType;
        }

        [$class, $entityType] = self::ENTITY_FIELDS[$field->fieldType];
        $property = (string) (((array) $field->config)['entityField'] ?? '');

        if ($property === '' || str_starts_with($property, '@')) {
            return self::COMPOSITE;
        }

        $type = App::i()->getRegisteredMetadata($class, $entityType)[$property]?->type ?? 'text';

        return in_array($type, ['', 'string'], true) ? 'text' : $type;
    }

    /** Se o campo pode alimentar a coluna, pelo tipo declarado de cada um. */
    public function fits(array $column, array $field): bool
    {
        if (in_array($field['fieldType'], self::MULTI_VALUE_FIELD_TYPES, true)) {
            return false;
        }

        $types = self::FIELD_TYPES_BY_COLUMN[$column['tipo'] ?? ''] ?? null;

        return $types === null || in_array($field['fieldType'], $types, true);
    }

    /** @return array<string, int> palpite por título e tipo; só vale depois que o gestor o confirma */
    private function suggestion(array $columns, array $fields): array
    {
        $suggestion = [];

        foreach ($columns as $column) {
            $words = $this->significantWords((string) ($column['nome'] ?? $column['coluna'] ?? ''));

            foreach ($fields as $field) {
                $title = $this->normalized($field['title']);

                if ($words && $this->fits($column, $field) && !array_filter($words, fn($word) => !str_contains($title, $word))) {
                    $suggestion[$column['coluna']] = $field['id'];
                    break;
                }
            }
        }

        return $suggestion;
    }

    /** @return array<string, string[]> por coluna de lista, os valores da ficha que ainda não têm opção do CultBR */
    private function pendingEquivalences(Opportunity $opportunity, array $columns, array $mapping): array
    {
        $pending = [];

        foreach ($columns as $column) {
            if (($column['tipo'] ?? null) !== 'lista') {
                continue;
            }

            $values = $this->valuesOf($opportunity, $column, $mapping['columns'][$column['coluna']] ?? null);
            $known = array_merge((array) ($column['opcoes'] ?? []), array_keys((array) ($mapping['equivalences'][$column['coluna']] ?? [])));
            $missing = array_values(array_diff($values, $known));

            if ($missing) {
                $pending[$column['coluna']] = $missing;
            }
        }

        return $pending;
    }

    // sem campo ligado, o tipo de agente vem do tipo de proponente, cujos rótulos são do Mapas
    private function valuesOf(Opportunity $opportunity, array $column, ?int $fieldId): array
    {
        if (!$fieldId) {
            return self::isAgentTypeColumn($column) ? (array) $opportunity->registrationProponentTypes : [];
        }

        return array_unique(array_filter(array_map(
            fn($meta) => is_scalar($meta->value) ? trim((string) $meta->value) : null,
            App::i()->repo(RegistrationMeta::class)->findBy(['key' => "field_{$fieldId}"]),
        )));
    }

    private function columnError(?array $column, ?array $field, mixed $fieldId): ?string
    {
        return match (true) {
            $column === null => i::__('Esta coluna não existe no modelo de planilha do edital.'),
            $fieldId === null => null,
            $field === null => i::__('Este campo não é da ficha deste edital.'),
            !$this->fits($column, $field) => i::__('O tipo deste campo não serve a esta coluna.'),
            default => null,
        };
    }

    private function documentError(string $type, string $kind, ?array $field, mixed $fieldId): ?string
    {
        return match (true) {
            !in_array($type, $this->proponentTypes(), true) => i::__('Tipo de proponente desconhecido.'),
            $fieldId === null => null,
            $field === null => i::__('Este campo não é da ficha deste edital.'),
            !$this->fits(['tipo' => $kind], $field) => i::__('O tipo deste campo não serve a este documento.'),
            default => null,
        };
    }

    private function significantWords(string $text): array
    {
        return array_values(array_filter(preg_split('/[^a-z0-9]+/', $this->normalized($text)), fn($word) => strlen($word) >= 3));
    }

    private function normalized(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);

        return trim(preg_replace('/^\s*\d+[.)]\s*/', '', $text));
    }
}
