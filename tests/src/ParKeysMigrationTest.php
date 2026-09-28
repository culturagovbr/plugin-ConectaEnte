<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Migrations\CopyLegacyParKeys;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * A cópia dos metadados do PAR para as chaves prefixadas, que roda uma vez por instalação.
 */
class ParKeysMigrationTest extends TestCase
{
    use PublicationRequirementsFixtures;

    const OLD_KEYS = [
        'parExercicioId' => CultBrMetadata::PAR_EXERCISE_ID,
        'parMetaId' => CultBrMetadata::PAR_GOAL_ID,
        'parAcaoId' => CultBrMetadata::PAR_ACTION_ID,
        'parAtividadeId' => CultBrMetadata::PAR_ACTIVITY_ID,
    ];

    function testTheValuesOfTheOldKeysAreReadByTheNewOnes()
    {
        $opportunity = $this->opportunityWithOldKeys(['parExercicioId' => '2024', 'parMetaId' => '7', 'parAcaoId' => '70', 'parAtividadeId' => '700']);

        $this->runMigration();

        foreach (['2024' => CultBrMetadata::PAR_EXERCISE_ID, '7' => CultBrMetadata::PAR_GOAL_ID, '70' => CultBrMetadata::PAR_ACTION_ID, '700' => CultBrMetadata::PAR_ACTIVITY_ID] as $value => $key) {
            $this->assertSame((string) $value, $this->storedValue($opportunity, $key), 'O que o CultEditais gravou continua valendo no ConectaEnte.');
        }
    }

    function testTheOldKeysAreKept()
    {
        $opportunity = $this->opportunityWithOldKeys(['parExercicioId' => '2024']);

        $this->runMigration();

        $this->assertSame('2024', $this->storedValue($opportunity, 'parExercicioId'), 'Quem roda o AldirBlanc junto continua lendo pelas chaves dele.');
    }

    function testAValueAlreadyInTheNewKeyIsNotOverwritten()
    {
        $opportunity = $this->opportunityWithOldKeys(['parExercicioId' => '2024']);
        $this->store($opportunity, CultBrMetadata::PAR_EXERCISE_ID, '2026');

        $this->runMigration();

        $this->assertSame('2026', $this->storedValue($opportunity, CultBrMetadata::PAR_EXERCISE_ID), 'A migração roda de novo sem desfazer escolha feita depois dela.');
    }

    function testAnOpportunityWithoutTheFederativeSealIsLeftAlone()
    {
        $opportunity = $this->coreCompleteOpportunity(Opportunity::STATUS_DRAFT);
        // a fixture já preenche as chaves prefixadas: o caso é a oportunidade que só tem as antigas
        $this->app->em->getConnection()->delete('opportunity_meta', ['object_id' => $opportunity->id, 'key' => CultBrMetadata::PAR_EXERCISE_ID]);
        $this->store($opportunity, 'parExercicioId', '2024');

        CopyLegacyParKeys::run();

        $this->assertNull(
            $this->storedValue($opportunity, CultBrMetadata::PAR_EXERCISE_ID),
            'O PAR de uma oportunidade do AldirBlanc segue sendo dele: copiar congelaria um valor que ele continua editando.',
        );
    }

    /** @param array<string, string> $values */
    private function opportunityWithOldKeys(array $values): Opportunity
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);

        foreach (CultBrMetadata::PAR_KEYS as $key) {
            $this->app->em->getConnection()->delete('opportunity_meta', ['object_id' => $opportunity->id, 'key' => $key]);
        }

        foreach ($values as $key => $value) {
            $this->store($opportunity, $key, $value);
        }

        return $opportunity;
    }

    private function store(Opportunity $opportunity, string $key, string $value): void
    {
        $this->app->em->getConnection()->insert('opportunity_meta', ['object_id' => $opportunity->id, 'key' => $key, 'value' => $value]);
    }

    private function storedValue(Opportunity $opportunity, string $key): ?string
    {
        return $this->app->em->getConnection()->fetchOne(
            'SELECT value FROM opportunity_meta WHERE object_id = ? AND key = ?',
            [$opportunity->id, $key],
        ) ?: null;
    }

    private function runMigration(): void
    {
        CopyLegacyParKeys::run();
    }

    function testTheDbUpdateRunsTheMigration()
    {
        $opportunity = $this->opportunityWithOldKeys(['parExercicioId' => '2024']);
        $updates = include PLUGINS_PATH . 'ConectaEnte/db-updates.php';
        $entrada = 'conectaente: copia os metadados do PAR para as chaves prefixadas';

        $this->assertArrayHasKey($entrada, $updates, 'Sem a entrada, a instalação existente nunca roda a cópia.');

        $updates[$entrada]();

        $this->assertSame('2024', $this->storedValue($opportunity, CultBrMetadata::PAR_EXERCISE_ID), 'A entrada precisa chamar a migração, não só existir.');
    }
}
