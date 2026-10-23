<?php

namespace ConectaEnte\Services;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Vocabulary\FundingSource;

/**
 * Saneia o nome digitado em "Recursos de outras fontes", que vai ao CultBR como nome_fonte.
 */
final class FundingSourceName
{
    const MAX_LENGTH = 255;

    /**
     * O bloco com os nomes das fontes aparados, sem HTML e no limite.
     */
    public function sanitizeBlock(mixed $block): mixed
    {
        $decoded = json_decode(json_encode($block), true);

        if (!is_array($decoded)) {
            return $block;
        }

        $sources = $decoded[FundingSource::OTHER_SOURCES->value] ?? null;

        if (!is_array($sources)) {
            return $block;
        }

        foreach ($sources as $index => $source) {
            if (is_array($source) && array_key_exists('nomeFonte', $source)) {
                $decoded[FundingSource::OTHER_SOURCES->value][$index]['nomeFonte'] = $this->sanitize($source['nomeFonte']);
            }
        }

        return $decoded;
    }

    /** O nome aparado, sem HTML e cortado no limite. */
    public function sanitize(mixed $name): string
    {
        $name = trim(strip_tags((string) $name));

        return mb_substr($name, 0, self::MAX_LENGTH);
    }

    /** Saneia o bloco da oportunidade, se houver o que sanear. */
    public function sanitizeOpportunity(object $opportunity): void
    {
        $block = $opportunity->{CultBrMetadata::FUNDING_SOURCES};

        if ($block !== null) {
            $opportunity->{CultBrMetadata::FUNDING_SOURCES} = $this->sanitizeBlock($block);
        }
    }
}
