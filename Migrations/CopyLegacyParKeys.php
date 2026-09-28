<?php

namespace ConectaEnte\Migrations;

use ConectaEnte\Metadata\CultBrMetadata;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;

/**
 * Copia os metadados do PAR gravados sem o prefixo do plugin para as chaves prefixadas.
 */
final class CopyLegacyParKeys
{
    /** As chaves que o AldirBlanc/Pnab usa, e que o plugin gravou antes de adotar o prefixo. */
    const LEGACY_KEYS = [
        'parExercicioId' => CultBrMetadata::PAR_EXERCISE_ID,
        'parMetaId' => CultBrMetadata::PAR_GOAL_ID,
        'parAcaoId' => CultBrMetadata::PAR_ACTION_ID,
        'parAtividadeId' => CultBrMetadata::PAR_ACTIVITY_ID,
    ];

    /** Só as seladas por Ente Federado: copiar de todas congelaria valor que o AldirBlanc segue editando. */
    public static function run(): int
    {
        $connection = App::i()->em->getConnection();
        $copied = 0;

        foreach (self::LEGACY_KEYS as $legacy => $prefixed) {
            // o valor já gravado na chave nova manda: a migração pode rodar de novo sem desfazer escolha posterior
            $copied += (int) $connection->executeStatement(
                "INSERT INTO opportunity_meta (object_id, key, value)
                 SELECT legado.object_id, ?, legado.value
                 FROM opportunity_meta legado
                 WHERE legado.key = ?
                   AND EXISTS (
                       SELECT 1
                       FROM seal_relation selo
                       JOIN conectaente_federative_entity_seal ente ON ente.seal_id = selo.seal_id
                       WHERE selo.object_id = legado.object_id
                         AND selo.object_type = ?
                         AND selo.status = 1
                   )
                   AND NOT EXISTS (
                       SELECT 1 FROM opportunity_meta atual
                       WHERE atual.object_id = legado.object_id AND atual.key = ?
                   )",
                [$prefixed, $legacy, Opportunity::class, $prefixed],
            );
        }

        return $copied;
    }
}
