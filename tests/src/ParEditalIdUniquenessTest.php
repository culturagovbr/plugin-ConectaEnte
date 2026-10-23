<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Migrations\ClaimParEditalId;
use ConectaEnte\Plugin;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\Entities\Opportunity;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * O edital do CultBR pertence a uma oportunidade só, e o banco passa a garantir isso.
 */
class ParEditalIdUniquenessTest extends TestCase
{
    use PublicationRequirementsFixtures;

    const INDEX = 'unq_conectaente_par_edital_id';

    // processo próprio: o insert recusado aborta a transação do teste e derrubaria os seguintes
    #[RunInSeparateProcess]
    function testTheIndexRefusesTheSecondOwnerOfTheSameEdital()
    {
        // cada metadado é gravado logo após criar a sua oportunidade: criar a seguinte desanexa a anterior
        $dona = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->writeRawMetadata($dona, CultBrMetadata::PAR_EDITAL_ID, '53781');
        $outra = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);

        $this->expectExceptionMessageMatches('/unq_conectaente_par_edital_id|duplicate key/i');

        $this->writeRawMetadata($outra, CultBrMetadata::PAR_EDITAL_ID, '53781');
    }

    // a instalação que já duplicou precisa ser limpa antes, senão o índice não nasce
    function testTheUpdateCleansTheDuplicatesBeforeCreatingTheIndex()
    {
        $this->dropIndex();
        $dona = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->writeRawMetadata($dona, CultBrMetadata::PAR_EDITAL_ID, '53781');
        Plugin::instance()->sendLogger()->startOrResume($dona);
        $copia = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->writeRawMetadata($copia, CultBrMetadata::PAR_EDITAL_ID, '53781');

        ClaimParEditalId::run();

        $this->assertSame('53781', $this->rawMetadata($dona), 'Quem enviou de fato é quem fica com o id.');
        $this->assertNull($this->rawMetadata($copia), 'A cópia sem envio perde o id que nunca foi dela.');
        $this->assertSame(0, $this->duplicatedValues(), 'Depois da limpeza o índice precisa poder nascer.');
    }

    private function dropIndex(): void
    {
        $this->app->em->getConnection()->executeStatement('DROP INDEX IF EXISTS ' . self::INDEX);
    }

    private function duplicatedValues(): int
    {
        return (int) $this->app->em->getConnection()->fetchOne(
            'SELECT count(*) FROM (SELECT value FROM opportunity_meta WHERE key = :key GROUP BY value HAVING count(*) > 1) duplicados',
            ['key' => CultBrMetadata::PAR_EDITAL_ID],
        );
    }

    private function rawMetadata(Opportunity $opportunity): ?string
    {
        return $this->app->em->getConnection()->fetchOne(
            'SELECT value FROM opportunity_meta WHERE object_id = :id AND key = :key',
            ['id' => $opportunity->id, 'key' => CultBrMetadata::PAR_EDITAL_ID],
        ) ?: null;
    }
}
