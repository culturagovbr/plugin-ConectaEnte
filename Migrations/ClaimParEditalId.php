<?php

namespace ConectaEnte\Migrations;

use ConectaEnte\Metadata\CultBrMetadata;
use MapasCulturais\App;

/**
 * Deixa cada edital do CultBR com uma dona só: a oportunidade que o enviou, ou a mais antiga.
 */
final class ClaimParEditalId
{
    /** Quem tem envio no histórico fica com o id; sem envio, decide a ordem de criação. */
    public static function run(): int
    {
        return (int) App::i()->em->getConnection()->executeStatement(
            "DELETE FROM opportunity_meta WHERE id IN (
                SELECT id FROM (
                    SELECT duplicada.id, row_number() OVER (
                        PARTITION BY duplicada.value
                        ORDER BY (
                            SELECT min(enviado.create_timestamp)
                            FROM conectaente_log enviado
                            WHERE enviado.opportunity_id = duplicada.object_id
                        ) NULLS LAST, duplicada.object_id
                    ) AS posicao
                    FROM opportunity_meta duplicada
                    WHERE duplicada.key = ?
                ) ranqueada WHERE posicao > 1
            )",
            [CultBrMetadata::PAR_EDITAL_ID],
        );
    }
}
