<?php

namespace ConectaEnte\Http;

/**
 * O que o CultBR diz numa recusa, nos três formatos da mesma rota: `errors[]`, `inconsistencias[]` e `detail`.
 */
final class ApiErrors
{
    const QUESTION_FIELD_PREFIX = 'questao:';

    /**
     * @param array<array{questionId: ?int, field: string, message: string}> $fields
     * @param array<array<string, mixed>> $rows
     */
    private function __construct(
        public readonly ?string $message,
        public readonly array $fields,
        public readonly array $rows,
        public readonly ?int $totalRows,
        public readonly ?string $detail,
    ) {
    }

    public static function fromBody(array $body): self
    {
        $message = is_string($body['message'] ?? null) && $body['message'] !== '' ? $body['message'] : null;

        return new self(
            message: $message,
            fields: self::fields($body['errors'] ?? null),
            rows: self::rows($body['inconsistencias'] ?? null),
            totalRows: is_int($body['total_inconsistencias'] ?? null) ? $body['total_inconsistencias'] : null,
            detail: self::detail($body['detail'] ?? null),
        );
    }

    /** Uma linha legível: o `detail`, a `message` ou o motivo genérico do fluxo, nessa ordem. */
    public function summary(string $fallback): string
    {
        return $this->detail ?? $this->message ?? $fallback;
    }

    private static function fields(mixed $errors): array
    {
        $fields = [];

        foreach (is_array($errors) ? $errors : [] as $error) {
            $field = is_array($error) && is_string($error['field'] ?? null) ? $error['field'] : null;
            $message = is_array($error) && is_string($error['message'] ?? null) ? $error['message'] : null;

            if ($field !== null && $message !== null) {
                $fields[] = ['questionId' => self::questionIdOf($field), 'field' => $field, 'message' => $message];
            }
        }

        return $fields;
    }

    private static function questionIdOf(string $field): ?int
    {
        $id = str_starts_with($field, self::QUESTION_FIELD_PREFIX) ? substr($field, strlen(self::QUESTION_FIELD_PREFIX)) : '';

        return ctype_digit($id) ? (int) $id : null;
    }

    private static function rows(mixed $rows): array
    {
        return array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
    }

    // `detail` é texto, ou a lista {loc, msg} do 422 de validação do corpo
    private static function detail(mixed $detail): ?string
    {
        if (is_string($detail) && $detail !== '') {
            return $detail;
        }

        $messages = is_array($detail) ? array_filter(array_map(fn($error) => self::fieldError($error), $detail)) : [];

        return $messages ? implode('; ', $messages) : null;
    }

    private static function fieldError(mixed $error): ?string
    {
        $message = is_array($error) ? ($error['msg'] ?? null) : null;

        if (!is_string($message) || $message === '') {
            return null;
        }

        $field = self::fieldName($error['loc'] ?? null);

        return $field ? "{$field}: {$message}" : $message;
    }

    // só erro no corpo é campo do edital: `header` é a credencial e `query` é a rota, e nomeá-los confundiria
    private static function fieldName(mixed $loc): ?string
    {
        if (!is_array($loc) || ($loc[0] ?? null) !== 'body') {
            return null;
        }

        $path = array_slice($loc, 1);

        return $path ? implode('.', $path) : null;
    }
}
