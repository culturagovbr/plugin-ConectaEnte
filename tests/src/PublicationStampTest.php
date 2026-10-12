<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Services\PublicationStamp;
use DateTime;
use MapasCulturais\Definitions\Metadata;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Entities\User;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

class PublicationStampTest extends TestCase
{
    use ConectaEnteFixtures;
    use PublicationRequirementsFixtures;
    use RequestFactory;

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

    // o gatilho do selo nao salva a oportunidade, entao o stamp() do save:before nao roda:
    // a data fica so na linha herdavel do Pnab, e o payload le outra chave
    function testSealingAPublishedOpportunityWithOnlyTheInheritableDateStillSends()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->completeOpportunity(Opportunity::STATUS_ENABLED);
        $this->forgetPluginDate($opportunity);
        $this->writeRawMetadata($opportunity, PublicationStamp::INHERITED_KEY, self::LEGACY_DATE);

        $opportunity = $this->reloaded($opportunity);
        $opportunity->createSealRelation($this->federativeSeal());

        $stamp = Plugin::instance()->publicationStamp();
        $this->assertTrue($stamp->hasPublicationDate($opportunity), 'Premissa: o gate aceita a data que ainda está só na linha herdável.');
        $this->assertNull($opportunity->getMetadata(CultBrMetadata::PUBLISHED_AT), 'Premissa: selar não passa pelo save:before, então nada carimbou.');

        $payload = Plugin::instance()->opportunityPayload()->build($opportunity);

        $this->assertNotNull(
            $payload['data_publicacao_edital'],
            'O gate deixou passar por causa da data herdável, e o payload tem que resolvê-la pela mesma regra.',
        );
    }

    // sem isto, fazer publicationDate() ignorar a data gravada e olhar so a herdavel
    // nao quebra nenhum teste deste arquivo — quem pegava era o teste do payload
    function testTheStampedDateWinsOverTheInheritableOne()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->completeOpportunity(Opportunity::STATUS_ENABLED);
        $this->writeRawMetadata($opportunity, PublicationStamp::INHERITED_KEY, self::LEGACY_DATE);

        $date = Plugin::instance()->publicationStamp()->publicationDate($this->reloaded($opportunity));

        $this->assertNotNull($date, 'A data gravada pela fixture é a que vale.');
        $this->assertNotSame(self::LEGACY_DATE, $date->format('Y-m-d H:i:s'), 'A herdável só entra quando não há data gravada.');
    }

    // este metodo roda no save, pelo gate de envio: ler pela propriedade magica faria o unserialize
    // lancar com data corrompida, e o gestor perderia o salvamento do edital
    function testACorruptedStampedDateDoesNotBreakTheSave()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->forgetPluginDate($opportunity);
        $this->writeRawMetadata($opportunity, CultBrMetadata::PUBLISHED_AT, 'isto nao e uma data');

        $stamp = Plugin::instance()->publicationStamp();
        $opportunity = $this->reloaded($opportunity);

        $this->assertNull($stamp->publicationDate($opportunity), 'Data ilegível é data ausente, não exceção.');
        $this->assertFalse($stamp->hasPublicationDate($opportunity), 'E o gate reprova por falta de data, que é o motivo verdadeiro.');
        $this->assertNull(
            Plugin::instance()->opportunityPayload()->build($opportunity)['data_publicacao_edital'],
            'O payload também não pode explodir: a chave sai nula e o gate já barrou o envio.',
        );
    }

    function testWithoutAnyDateThereIsNothingToResolve()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->completeOpportunity(Opportunity::STATUS_DRAFT);
        $this->forgetPluginDate($opportunity);

        $stamp = Plugin::instance()->publicationStamp();
        $opportunity = $this->reloaded($opportunity);

        $this->assertNull($stamp->publicationDate($opportunity), 'Sem data gravada nem herdável, não há o que resolver.');
        $this->assertFalse($stamp->hasPublicationDate($opportunity), 'E o gate tem que concordar com isso.');
    }

    // a linha tem que deixar de existir: linha com null e data apagada, que nao se herda
    private function forgetPluginDate(Opportunity $opportunity): void
    {
        $this->app->em->getConnection()->executeStatement(
            'DELETE FROM opportunity_meta WHERE object_id = ? AND "key" = ?',
            [$opportunity->id, CultBrMetadata::PUBLISHED_AT],
        );
    }

    function testCopyOfAStampedOpportunityGetsItsOwnDateWhenPublished()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->federativeSeal();
        $source = $this->sealedDraft($seal);
        $source->conectaente_publishedAt = new DateTime(self::OLD_DATE);
        $source->publish(true);

        $copy = $this->duplicate($source);
        $this->assertNull($copy->getMetadata('conectaente_publishedAt'));

        $copy->createSealRelation($this->reloadedSeal($seal));
        $copy->publish(true);

        $this->assertStampedNow($this->reloaded($copy));
    }

    function testCopyDoesNotInheritThePublishedTimestampThatCameAlong()
    {
        $this->loginAsSaasSuperAdmin();
        $this->registerPublishedTimestampLikeAldirBlanc();
        $seal = $this->federativeSeal();
        $source = $this->createOpportunityWithSeal($seal);
        $this->writeRawMetadata($source, 'publishedTimestamp', self::LEGACY_DATE);

        $copy = $this->duplicate($source);
        $copy->createSealRelation($this->reloadedSeal($seal));
        $copy->publish(true);

        $this->assertStampedNow($this->reloaded($copy));
    }

    function testDuplicationLeavesTheSourceDateUntouched()
    {
        $this->loginAsSaasSuperAdmin();
        $source = $this->sealedDraft();
        $source->conectaente_publishedAt = new DateTime(self::OLD_DATE);
        $source->publish(true);

        $this->duplicate($source);

        $this->assertSame(self::OLD_DATE, $this->publishedAt($source));
    }

    function testDuplicationMarkSparesTheSourceAndThePhases()
    {
        $this->loginAsSaasSuperAdmin();
        $source = $this->sealedDraft();
        $source->conectaente_publishedAt = new DateTime(self::OLD_DATE);
        $phase = $source->lastPhase;
        $phase->conectaente_publishedAt = new DateTime(self::OLD_DATE);
        $stamp = Plugin::instance()->publicationStamp();

        $stamp->duplicationStarted($source);
        try {
            $stamp->stamp($source);
            $stamp->stamp($phase);
        } finally {
            $stamp->duplicationFinished();
        }

        $this->assertSame(self::OLD_DATE, $source->getMetadata('conectaente_publishedAt'));
        $this->assertSame(self::OLD_DATE, $phase->getMetadata('conectaente_publishedAt'));
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

    private function registerPublishedTimestampLikeAldirBlanc(): void
    {
        if (!$this->app->getRegisteredMetadataByMetakey('publishedTimestamp', Opportunity::class)) {
            $this->app->registerMetadata(new Metadata('publishedTimestamp', [
                'label' => 'Data de publicação do edital',
                'type' => 'DateTime',
                'private' => true,
            ]), Opportunity::class);
        }
    }

    private function duplicate(Opportunity $source): Opportunity
    {
        // a duplicação parte do EntityManager vazio, como uma requisição em produção
        $this->app->em->clear();
        $this->login($this->app->repo(User::class)->find($this->app->user->id));

        $this->assertStatus200($this->requestFactory->POST('opportunity', 'duplicate', [$source->id]));

        return $this->app->repo(Opportunity::class)->findOneBy(['parent' => null], ['id' => 'DESC']);
    }

    private function reloadedSeal(Seal $seal): Seal
    {
        return $this->app->repo(Seal::class)->find($seal->id);
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
