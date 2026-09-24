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

    public function __construct(private SealedOpportunity $sealedOpportunity)
    {
    }

    /**
     * Grava a data de publicação da oportunidade selada: herda o publishedTimestamp uma vez ou marca a passagem para publicado.
     */
    public function stamp(Opportunity $opportunity): void
    {
        if ($opportunity->getMetadata(CultBrMetadata::PUBLISHED_AT) || !$this->sealedOpportunity->isSealed($opportunity)) {
            return;
        }

        $inherited = $this->hasPublishedAtRow($opportunity) ? null : $this->inheritedDate($opportunity);

        if ($inherited) {
            $opportunity->{CultBrMetadata::PUBLISHED_AT} = $inherited;
        } elseif ($this->isBeingPublished($opportunity)) {
            $opportunity->{CultBrMetadata::PUBLISHED_AT} = new DateTime();
        }
    }

    // a linha com null é data apagada, e apagada não se herda de novo
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
        $original = App::i()->em->getUnitOfWork()->getOriginalEntityData($opportunity);

        return (int) $opportunity->status === Opportunity::STATUS_ENABLED
            && (int) ($original['status'] ?? Opportunity::STATUS_DRAFT) !== Opportunity::STATUS_ENABLED;
    }
}
