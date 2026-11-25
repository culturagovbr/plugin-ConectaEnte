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
    const MIRRORED_TITLE = '_espelho_numero_titulo';
    const MIRRORED_DETAIL = '_espelho_detalhamento_objeto';
    const TRANSFERRED_PREFIX = 'valor_total_repassado';
    // a única tabela de categorias que vai como linha única quando o edital não tem faixas
    const SINGLE_ROW_CATEGORY_TAG = 'dados_por_categoria_resultado_1_1';
    const COMMITTEE_TAG = 'comissao_avaliadora';
    const SPREADSHEET_TAG = 'planilha_de_resultados_conforme_padrao_de_dados';
    const SPREADSHEET_UPLOAD_PREFIX = 'upload_planilha_csv';
    // as duas tags de anexo que o contrato dispensa
    const DISPENSED_ATTACHMENT_TAGS = [self::SPREADSHEET_TAG, 'planilha_pdf_de_comissao_avaliadora'];

    /** @var array<int, array> por id_questao_etapa */
    private array $questions = [];

    public function __construct(public readonly array $structure, public readonly bool $hasSpreadsheet = false)
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

    /** A questão de anexo que o CultBR preenche com as linhas da planilha. */
    public function isFilledByLines(array $question): bool
    {
        $tag = (string) ($question['tag'] ?? '');

        return $this->hasSpreadsheet && ($tag === self::SPREADSHEET_TAG || str_starts_with($tag, self::SPREADSHEET_UPLOAD_PREFIX));
    }

    /** Anexo que a estrutura marca como obrigatório e a API não cobra. */
    public function isDispensed(array $question): bool
    {
        return $this->isFilledByLines($question) || in_array($question['tag'] ?? null, self::DISPENSED_ATTACHMENT_TAGS, true);
    }

    /** Anexo não cobrado que não cabe no envio: as linhas vão em JSON, e o arquivo só vai em multipart. */
    public function isAttachmentUnavailable(array $question): bool
    {
        return $this->hasSpreadsheet
            && in_array($question['tipo_questao'] ?? null, ResultValidator::ATTACHMENT_TYPES, true)
            && (empty($question['requerida']) || $this->isDispensed($question));
    }

    /** @return int[] */
    public function dispensedIds(): array
    {
        return $this->idsWhere($this->isDispensed(...));
    }

    /** @return int[] */
    public function filledByLinesIds(): array
    {
        return $this->idsWhere($this->isFilledByLines(...));
    }

    /** @return int[] as tabelas da comissão avaliadora */
    public function committeeIds(): array
    {
        return $this->idsWhere(fn($question) => str_contains((string) ($question['tag'] ?? ''), self::COMMITTEE_TAG) && in_array($question['tipo_questao'] ?? null, ResultValidator::TABLE_TYPES, true));
    }

    /** @return int[] */
    public function unavailableAttachmentIds(): array
    {
        return $this->idsWhere($this->isAttachmentUnavailable(...));
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

    /** O espelho do edital que a questão mostra, ou null se o Mapas não tem esse dado. */
    public function mirrorOf(array $question): ?string
    {
        $tag = (string) ($question['tag'] ?? '');

        return match (true) {
            !$this->isReadOnly($question) => null,
            str_contains($tag, self::MIRRORED_TITLE) => self::MIRRORED_TITLE,
            str_contains($tag, self::MIRRORED_DETAIL) => self::MIRRORED_DETAIL,
            default => null,
        };
    }

    private function idsWhere(callable $matches): array
    {
        return array_keys(array_filter($this->questions, $matches));
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
