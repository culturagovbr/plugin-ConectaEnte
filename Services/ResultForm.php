<?php

namespace ConectaEnte\Services;

/**
 * A estrutura do formulário de resultado lida como lista de questões, com o papel de cada uma pela tag.
 */
final class ResultForm
{
    const ENROLLED_PREFIX = 'total_inscritos';
    const AWARDED_PREFIXES = ['total_contemplados', 'total_vagas_contempladas'];
    const CATEGORY_PREFIXES = ['dados_por_categoria', 'tabela_categorias'];
    const TRANSFERRED_PREFIX = 'valor_total_repassado';
    // a única tabela de categorias que vai como linha única quando o edital não tem faixas
    const SINGLE_ROW_CATEGORY_TAG = 'dados_por_categoria_resultado_1_1';

    /** @var array<int, array> por id_questao_etapa */
    private array $questions = [];

    public function __construct(public readonly array $structure)
    {
        foreach ($structure['secoes'] ?? [] as $section) {
            foreach ($section['etapas'] ?? [] as $step) {
                foreach ($step['questoes'] ?? [] as $question) {
                    $this->questions[(int) $question['id_questao_etapa']] = $question;
                }
            }
        }
    }

    public function versionId(): ?int
    {
        return isset($this->structure['id_formulario_versao']) ? (int) $this->structure['id_formulario_versao'] : null;
    }

    /** @return array<int, array> */
    public function questions(): array
    {
        return $this->questions;
    }

    public function question(int $id): ?array
    {
        return $this->questions[$id] ?? null;
    }

    /** Calculada pelo Mapas e só leitura: inscritos, contemplados e as tabelas de categorias. */
    public function isAutomatic(array $question): bool
    {
        $tag = (string) ($question['tag'] ?? '');

        return str_starts_with($tag, self::ENROLLED_PREFIX)
            || $this->startsWithAny($tag, self::AWARDED_PREFIXES)
            || $this->startsWithAny($tag, self::CATEGORY_PREFIXES);
    }

    /** Calculada pelo Mapas como sugestão, e editável. */
    public function isSuggested(array $question): bool
    {
        return str_starts_with((string) ($question['tag'] ?? ''), self::TRANSFERRED_PREFIX);
    }

    public function isReadOnly(array $question): bool
    {
        return !empty($question['somente_leitura']);
    }

    public function hasTag(array $question, string $prefix): bool
    {
        return str_starts_with((string) ($question['tag'] ?? ''), $prefix);
    }

    public function hasAnyTag(array $question, array $prefixes): bool
    {
        return $this->startsWithAny((string) ($question['tag'] ?? ''), $prefixes);
    }

    private function startsWithAny(string $tag, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($tag, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
