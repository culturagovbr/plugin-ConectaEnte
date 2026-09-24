<?php

namespace ConectaEnte\Services;

use ConectaEnte\Metadata\CultBrMetadata;
use DateTime;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\OpportunityMeta;

final class PublicationStamp
{
    const INHERITED_KEY = 'publishedTimestamp';

    private ?int $duplicationSourceId = null;

    public function __construct(private SealedOpportunity $sealedOpportunity)
    {
    }

    /**
     * Grava a data de publicação da oportunidade selada: herda o publishedTimestamp uma vez ou marca a passagem para publicado.
     */
    public function stamp(Opportunity $opportunity): void
    {
        if ($this->isCopyBeingDuplicated($opportunity)) {
            $this->clearCopiedDates($opportunity);
            return;
        }

        if ($opportunity->getMetadata(CultBrMetadata::PUBLISHED_AT) || !$this->sealedOpportunity->isSealed($opportunity)) {
            return;
        }

        $inherited = $this->inheritableDate($opportunity);

        if ($inherited) {
            $opportunity->{CultBrMetadata::PUBLISHED_AT} = $inherited;
        } elseif ($this->isBeingPublished($opportunity)) {
            $opportunity->{CultBrMetadata::PUBLISHED_AT} = new DateTime();
        }
    }

    /**
     * Se a oportunidade tem data de publicação, gravada ou ainda por herdar do publishedTimestamp.
     */
    public function hasPublicationDate(Opportunity $opportunity): bool
    {
        return $opportunity->getMetadata(CultBrMetadata::PUBLISHED_AT) || $this->inheritableDate($opportunity);
    }

    /**
     * Se a oportunidade já estava publicada antes das alterações desta requisição.
     */
    public function wasPublished(Opportunity $opportunity): bool
    {
        $original = App::i()->em->getUnitOfWork()->getOriginalEntityData($opportunity);

        return (int) ($original['status'] ?? Opportunity::STATUS_DRAFT) === Opportunity::STATUS_ENABLED;
    }

    /**
     * Marca o início da duplicação da oportunidade informada.
     */
    public function duplicationStarted(Opportunity $source): void
    {
        $this->duplicationSourceId = $source->id;
    }

    /**
     * Encerra a marca de duplicação, no fim da requisição.
     */
    public function duplicationFinished(): void
    {
        $this->duplicationSourceId = null;
    }

    private function isCopyBeingDuplicated(Opportunity $opportunity): bool
    {
        return $this->duplicationSourceId !== null && $opportunity->id !== $this->duplicationSourceId && !$opportunity->parent;
    }

    private function clearCopiedDates(Opportunity $copy): void
    {
        $copy->{CultBrMetadata::PUBLISHED_AT} = null;

        if ($copy->getRegisteredMetadata(self::INHERITED_KEY, true)) {
            $copy->setMetadata(self::INHERITED_KEY, null);
        }
    }

    // a linha com null é data apagada, e apagada não se herda de novo
    private function inheritableDate(Opportunity $opportunity): ?DateTime
    {
        return $this->hasPublishedAtRow($opportunity) ? null : $this->inheritedDate($opportunity);
    }

    private function hasPublishedAtRow(Opportunity $opportunity): bool
    {
        return $this->metadataRow($opportunity, CultBrMetadata::PUBLISHED_AT) !== null;
    }

    private function inheritedDate(Opportunity $opportunity): ?DateTime
    {
        $value = $this->metadataRow($opportunity, self::INHERITED_KEY)?->value;

        return $value ? (DateTime::createFromFormat('Y-m-d H:i:s', $value) ?: null) : null;
    }

    // lê a linha crua: sem o AldirBlanc, o publishedTimestamp não é registrado
    private function metadataRow(Opportunity $opportunity, string $key): ?OpportunityMeta
    {
        return App::i()->repo(OpportunityMeta::class)->findOneBy(['owner' => $opportunity, 'key' => $key]);
    }

    private function isBeingPublished(Opportunity $opportunity): bool
    {
        return (int) $opportunity->status === Opportunity::STATUS_ENABLED && !$this->wasPublished($opportunity);
    }
}
