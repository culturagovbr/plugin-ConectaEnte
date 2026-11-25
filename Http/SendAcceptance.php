<?php

namespace ConectaEnte\Http;

final class SendAcceptance
{
    const OPPORTUNITY = 'opportunity';
    const RESULT = 'result';

    private const ACCEPTED_STATUS = [
        self::OPPORTUNITY => 200,
        self::RESULT => 201,
    ];

    /** Se a resposta é um aceite da operação: o status dela e, no edital, o `id_par_edital` inteiro positivo. */
    public static function isAccepted(string $operation, ?int $status, ?string $body): bool
    {
        if ($status !== self::ACCEPTED_STATUS[$operation]) {
            return false;
        }

        return $operation === self::RESULT || self::parEditalIdOf($body) !== null;
    }

    public static function parEditalIdOf(?string $body): ?int
    {
        $decoded = $body === null ? null : json_decode($body, true);

        // json_decode leva {} e [] ao mesmo array vazio: nenhum dos dois é objeto com o id
        if (!is_array($decoded) || array_is_list($decoded)) {
            return null;
        }

        $id = $decoded['id_par_edital'] ?? null;

        return is_int($id) && $id > 0 ? $id : null;
    }
}
