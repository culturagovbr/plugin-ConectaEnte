<?php

namespace Tests\ConectaEnte;

use DateTime;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\OpportunityMeta;
use MapasCulturais\Entities\Seal;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;

class PublicationStampTest extends TestCase
{
    use ConectaEnteFixtures;

    const OLD_DATE = '2025-03-10 09:00:00';
    const LEGACY_DATE = '2024-11-05 14:20:00';

    function testPublishingASealedDraftStampsThePublicationDate()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->sealedDraft();

        $opportunity->publish(true);

        $this->assertStampedNow($this->reloaded($opportunity));
    }

    function testSavingAnOpportunityThatWasAlreadyPublishedDoesNotStamp()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunityWithSeal($this->federativeSeal());

        $opportunity->shortDescription = 'Edital publicado antes do selo.';
        $opportunity->save(true);

        $this->assertNull($this->reloaded($opportunity)->getMetadata('conectaente_publishedAt'));
    }

    function testExistingDateIsKeptWhenPublishing()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->sealedDraft();
        $opportunity->conectaente_publishedAt = new DateTime(self::OLD_DATE);
        $opportunity->save(true);

        $opportunity->publish(true);

        $this->assertSame(self::OLD_DATE, $this->publishedAt($opportunity));
    }

    function testInheritsThePublishedTimestampWhileThePluginNeverHadADate()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->sealedDraft();
        $this->writeRawMetadata($opportunity, 'publishedTimestamp', self::LEGACY_DATE);

        $this->reloaded($opportunity)->save(true);

        $this->assertSame(self::LEGACY_DATE, $this->publishedAt($opportunity));
    }

    function testErasedPluginDateIsNotInheritedAgain()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->sealedDraft();
        $this->writeRawMetadata($opportunity, 'publishedTimestamp', self::LEGACY_DATE);
        $this->writeRawMetadata($opportunity, 'conectaente_publishedAt', null);

        $this->reloaded($opportunity)->save(true);

        $this->assertNull($this->reloaded($opportunity)->getMetadata('conectaente_publishedAt'));
    }

    function testPluginDateWinsOverThePublishedTimestamp()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->sealedDraft();
        $opportunity->conectaente_publishedAt = new DateTime(self::OLD_DATE);
        $opportunity->save(true);
        $this->writeRawMetadata($opportunity, 'publishedTimestamp', self::LEGACY_DATE);

        $this->reloaded($opportunity)->save(true);

        $this->assertSame(self::OLD_DATE, $this->publishedAt($opportunity));
    }

    function testUnreadablePublishedTimestampIsNotInheritedAndDoesNotBreakTheSave()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->sealedDraft();
        $this->writeRawMetadata($opportunity, 'publishedTimestamp', 'ontem à tarde');

        $this->reloaded($opportunity)->save(true);

        $this->assertNull($this->reloaded($opportunity)->getMetadata('conectaente_publishedAt'));
    }

    function testUnsealedOpportunityIsNotStamped()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity(Opportunity::STATUS_DRAFT);

        $opportunity->publish(true);

        $this->assertNull($this->reloaded($opportunity)->getMetadata('conectaente_publishedAt'));
    }

    function testPhaseOfSealedOpportunityDoesNotInherit()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->federativeSeal();
        $phase = $this->sealedDraft($seal)->lastPhase;
        // a fase também recebe o selo, para só a guarda de fase impedir a herança
        $phase->createSealRelation($seal);
        $this->writeRawMetadata($phase, 'publishedTimestamp', self::LEGACY_DATE);

        $this->reloaded($phase)->save(true);

        $this->assertNull($this->reloaded($phase)->getMetadata('conectaente_publishedAt'));
    }

    private function federativeSeal(): Seal
    {
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);

        return $seal;
    }

    private function sealedDraft(?Seal $seal = null): Opportunity
    {
        return $this->createOpportunityWithSeal($seal ?? $this->federativeSeal(), Opportunity::STATUS_DRAFT);
    }

    private function writeRawMetadata(Opportunity $opportunity, string $key, ?string $value): void
    {
        $meta = new OpportunityMeta;
        $meta->owner = $opportunity;
        $meta->key = $key;
        $meta->value = $value;
        $meta->save(true);
    }

    private function publishedAt(Opportunity $opportunity): ?string
    {
        return $this->reloaded($opportunity)->conectaente_publishedAt?->format('Y-m-d H:i:s');
    }

    private function assertStampedNow(Opportunity $opportunity): void
    {
        $stamped = $opportunity->conectaente_publishedAt;

        $this->assertInstanceOf(DateTime::class, $stamped);
        $this->assertLessThan(60, abs($stamped->getTimestamp() - time()));
    }
}
