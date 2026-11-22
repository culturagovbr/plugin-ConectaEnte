<?php

namespace ConectaEnte\Services;

use DateTime;
use MapasCulturais\i;

/**
 * Valida as respostas pelo que a estrutura do formulário declara: tipo, limites, opções, regras e documentos.
 */
final class ResultValidator
{
    const ATTACHMENT_TYPES = ['anexo', 'múltiplos anexos'];
    const CHOICE_LIST_TYPES = ['múltipla escolha', 'checkbox'];
    const DOCUMENT_COLUMNS = ['cpf', 'cnpj'];
    const DOCUMENT_MASKS = ['documento', 'cpf', 'cnpj'];

    public function __construct(private ResultForm $form)
    {
    }

    /** Se as regras de visibilidade deixam a questão à mostra, dadas as respostas atuais. */
    public function isVisible(array $question, array $answers): bool
    {
        foreach ($question['config']['regras']['itens'] ?? [] as $rule) {
            if (($rule['tipo'] ?? null) !== 'visibilidade') {
                continue;
            }

            $matches = $this->matches($rule['quando'] ?? [], $answers);
            $shows = (bool) ($rule['efeito']['mostrar'] ?? true);

            if ($matches !== $shows) {
                return false;
            }
        }

        return true;
    }

    /** @return int[] obrigatórias visíveis e editáveis ainda sem resposta */
    public function missing(array $answers, array $computed): array
    {
        $known = $computed + $answers;
        $missing = [];

        foreach ($this->form->questions() as $id => $question) {
            $required = !empty($question['requerida']) && !$this->form->isReadOnly($question) && !array_key_exists($id, $computed);

            if ($required && !$this->isAttachment($question) && $this->isVisible($question, $known) && self::isEmpty($answers[$id] ?? null)) {
                $missing[] = $id;
            }
        }

        return $missing;
    }

    /** O motivo da recusa de uma resposta, ou null se ela cabe no que a questão declara. */
    public function error(array $question, mixed $value): ?string
    {
        if (self::isEmpty($value) || $this->isAttachment($question)) {
            return null;
        }

        return match ($question['tipo_questao'] ?? null) {
            'texto', 'textarea' => $this->textError($question, $value),
            'número' => $this->numberError($question, $value),
            'moeda' => is_numeric($value) && $value >= 0 ? null : i::__('Informe um valor em reais, sem sinal negativo.'),
            'data' => $this->isDate($value) ? null : i::__('Informe uma data válida.'),
            'booleano' => is_bool($value) ? null : i::__('Responda sim ou não.'),
            'radio' => $this->choiceError($question, $value),
            'múltipla escolha', 'checkbox' => $this->choiceListError($question, $value),
            'tabela' => $this->tableError($question, $value),
            'Tabela Dinâmica' => $this->dynamicTableError($question, $value),
            default => sprintf(i::__('O tipo de questão "%s" não é suportado nesta versão do plugin.'), (string) ($question['tipo_questao'] ?? '')),
        };
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /** O id da opção escolhida, venha ela sozinha ou com o texto do complemento. */
    public static function choiceId(mixed $choice): mixed
    {
        return is_array($choice) ? ($choice['id'] ?? null) : $choice;
    }

    public static function isValidCpf(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value);

        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;

            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $digits[$i] * ($length + 1 - $i);
            }

            if ((int) $digits[$length] !== ($sum * 10) % 11 % 10) {
                return false;
            }
        }

        return true;
    }

    // o CNPJ alfanumérico usa o mesmo cálculo, com cada caractere valendo o código ASCII menos 48
    public static function isValidCnpj(string $value): bool
    {
        $cnpj = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $value));

        if (!preg_match('/^[0-9A-Z]{12}\d{2}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        foreach ([12, 13] as $length) {
            $sum = 0;

            for ($i = 0; $i < $length; $i++) {
                $sum += (ord($cnpj[$i]) - 48) * [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2][$i + 13 - $length];
            }

            $digit = $sum % 11 < 2 ? 0 : 11 - $sum % 11;

            if ((int) $cnpj[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private function matches(array $condition, array $answers): bool
    {
        $source = $answers[(int) ($condition['fonteIdQuestaoEtapa'] ?? 0)] ?? null;
        $expected = $condition['valor'] ?? null;

        return match ($condition['operador'] ?? null) {
            'eq' => self::choiceId($source) === $expected,
            'includes' => is_array($source) && in_array($expected, array_map([self::class, 'choiceId'], $source), true),
            default => false,
        };
    }

    private function isAttachment(array $question): bool
    {
        return in_array($question['tipo_questao'] ?? null, self::ATTACHMENT_TYPES, true);
    }

    private function textError(array $question, mixed $value): ?string
    {
        if (!is_string($value)) {
            return i::__('Responda com texto.');
        }

        $limit = $question['config']['limiteCaracteres'] ?? $question['valor_maximo'] ?? null;

        if ($limit && mb_strlen($value) > (int) $limit) {
            return sprintf(i::__('Use no máximo %d caracteres.'), (int) $limit);
        }

        $isCep = ($question['mascara'] ?? null) === 'cep' || ($question['config']['padraoValidacao'] ?? null) === 'cep';

        return $isCep && !preg_match('/^\d{8}$/', preg_replace('/\D/', '', $value)) ? i::__('Informe um CEP com 8 dígitos.') : null;
    }

    private function numberError(array $question, mixed $value): ?string
    {
        $config = $question['config'] ?? [];
        $minimum = $config['numericoMinimo'] ?? $question['valor_minimo'] ?? null;
        $maximum = $question['valor_maximo'] ?? null;

        return match (true) {
            !is_numeric($value) => i::__('Informe um número.'),
            !empty($config['numericoSomenteInteiros']) && (float) $value != (int) $value => i::__('Informe um número inteiro.'),
            $minimum !== null && $value < $minimum => sprintf(i::__('Informe um número a partir de %s.'), $minimum),
            $maximum !== null && $value > $maximum => sprintf(i::__('Informe um número até %s.'), $maximum),
            default => null,
        };
    }

    private function isDate(mixed $value): bool
    {
        foreach (['Y-m-d', 'Y-m-d\TH:i', 'Y-m-d H:i'] as $format) {
            $date = is_string($value) ? DateTime::createFromFormat($format, $value) : false;

            if ($date && $date->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    private function choiceError(array $question, mixed $choice): ?string
    {
        $item = $this->item($question, self::choiceId($choice));

        if (!$item) {
            return i::__('Escolha uma das opções da lista.');
        }

        if (($item['tipo'] ?? null) !== 'campoComplementar') {
            return null;
        }

        $complement = is_array($choice) ? ($choice['complemento'] ?? null) : null;
        $limit = (int) ($item['campoComplementar']['limiteCaracteres'] ?? 0);

        return match (true) {
            !is_string($complement) || trim($complement) === '' => sprintf(i::__('Preencha "%s".'), $item['campoComplementar']['nome'] ?? $item['label']),
            $limit > 0 && mb_strlen($complement) > $limit => sprintf(i::__('Use no máximo %d caracteres.'), $limit),
            default => null,
        };
    }

    private function choiceListError(array $question, mixed $choices): ?string
    {
        if (!is_array($choices) || !array_is_list($choices)) {
            return i::__('Marque as opções da lista.');
        }

        foreach ($choices as $choice) {
            if ($error = $this->choiceError($question, $choice)) {
                return $error;
            }
        }

        $options = $question['opcao'] ?? [];
        $exclusive = array_filter($choices, fn($choice) => !empty($this->item($question, self::choiceId($choice))['exclusiva']));

        return match (true) {
            $exclusive && count($choices) > 1 => i::__('Esta opção não pode ser marcada junto de outras.'),
            isset($options['limiteMarcacoes']) && count($choices) > $options['limiteMarcacoes'] => sprintf(i::__('Marque no máximo %d opções.'), $options['limiteMarcacoes']),
            isset($options['limiteMinimoMarcacoes']) && count($choices) < $options['limiteMinimoMarcacoes'] => sprintf(i::__('Marque ao menos %d opções.'), $options['limiteMinimoMarcacoes']),
            default => null,
        };
    }

    private function item(array $question, mixed $id): ?array
    {
        foreach ($question['opcao']['itens'] ?? [] as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    private function tableError(array $question, mixed $rows): ?string
    {
        $table = $question['config']['tabela'] ?? [];

        return $this->rowsError($rows, $table['minLinhas'] ?? null, $table['maxLinhas'] ?? null)
            ?? $this->cellsError($rows, $table['colunas'] ?? [], 'id', fn($column) => [
                'required' => !empty($column['obrigatoria']),
                'max' => $column['maxCaracteres'] ?? null,
                'min' => $column['minCaracteres'] ?? null,
                'options' => $column['opcoes'] ?? null,
                'number' => ($column['tipo'] ?? null) === 'number',
                'document' => in_array($column['id'] ?? null, self::DOCUMENT_COLUMNS, true) ? $column['id'] : null,
            ]);
    }

    private function dynamicTableError(array $question, mixed $rows): ?string
    {
        $config = $question['config'] ?? [];

        return $this->rowsError($rows, $config['minRows'] ?? null, $config['maxRows'] ?? null)
            ?? $this->cellsError($rows, $config['columns'] ?? [], 'key', fn($column) => [
                'required' => !empty($column['required']),
                'max' => ($column['type'] ?? null) === 'text' ? ($column['max'] ?? null) : null,
                'min' => null,
                'options' => $column['dropdownOptions']['values'] ?? null,
                'number' => in_array($column['type'] ?? null, ['number', 'currency'], true),
                'document' => in_array($column['mask'] ?? null, self::DOCUMENT_MASKS, true) ? $column['mask'] : null,
            ]);
    }

    private function rowsError(mixed $rows, ?int $minimum, ?int $maximum): ?string
    {
        return match (true) {
            !is_array($rows) || !array_is_list($rows) || array_filter($rows, fn($row) => !is_array($row)) => i::__('Preencha a tabela linha a linha.'),
            $minimum !== null && count($rows) < $minimum => sprintf(i::__('Preencha ao menos %d linhas.'), $minimum),
            $maximum !== null && count($rows) > $maximum => sprintf(i::__('Preencha no máximo %d linhas.'), $maximum),
            default => null,
        };
    }

    private function cellsError(array $rows, array $columns, string $idKey, callable $rules): ?string
    {
        foreach ($rows as $index => $row) {
            foreach ($columns as $column) {
                $error = $this->cellError($row[$column[$idKey] ?? ''] ?? null, $rules($column));

                if ($error) {
                    return sprintf(i::__('Linha %d, %s: %s'), $index + 1, $column['nome'] ?? $column['label'] ?? $column[$idKey], $error);
                }
            }
        }

        return null;
    }

    private function cellError(mixed $value, array $rule): ?string
    {
        if (self::isEmpty($value)) {
            return $rule['required'] ? i::__('preenchimento obrigatório.') : null;
        }

        $text = is_scalar($value) ? (string) $value : '';

        return match (true) {
            $rule['number'] && !is_numeric($value) => i::__('informe um número.'),
            $rule['max'] && mb_strlen($text) > $rule['max'] => sprintf(i::__('use no máximo %d caracteres.'), $rule['max']),
            $rule['min'] && mb_strlen($text) < $rule['min'] => sprintf(i::__('use ao menos %d caracteres.'), $rule['min']),
            is_array($rule['options']) && $rule['options'] && !in_array($value, $rule['options'], true) => i::__('escolha uma das opções.'),
            $rule['document'] !== null && !$this->isValidDocument($text, $rule['document']) => i::__('documento inválido.'),
            default => null,
        };
    }

    private function isValidDocument(string $value, string $kind): bool
    {
        $isCnpjShaped = strlen(preg_replace('/[^0-9A-Za-z]/', '', $value)) === 14;

        return match ($kind) {
            'cpf' => self::isValidCpf($value),
            'cnpj' => self::isValidCnpj($value),
            default => $isCnpjShaped ? self::isValidCnpj($value) : self::isValidCpf($value),
        };
    }
}
