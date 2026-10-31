<?php

namespace ConectaEnte\Services;

use ConectaEnte\Vocabulary\FundingSource;

/**
 * Saneia o nome digitado em "Recursos de outras fontes", que vai ao CultBR como `nome_fonte`.
 */
class FundingSourceName
{
    const MAX_LENGTH = 255;

    /**
     * O bloco com os nomes das fontes aparados, sem HTML e no limite. Um valor que não é bloco volta como veio.
     *
     * @param mixed $block lista associativa já decodificada
     *
     * @return mixed
     */
    public function sanitizeBlock($block)
    {
        if (!is_array($block) || !isset($block[FundingSource::OTHER_SOURCES]) || !is_array($block[FundingSource::OTHER_SOURCES])) {
            return $block;
        }

        foreach ($block[FundingSource::OTHER_SOURCES] as $index => $source) {
            if (is_array($source) && array_key_exists('nomeFonte', $source)) {
                $block[FundingSource::OTHER_SOURCES][$index]['nomeFonte'] = $this->sanitize($source['nomeFonte']);
            }
        }

        return $block;
    }

    /**
     * O nome aparado, sem HTML e cortado no limite.
     *
     * @param mixed $name
     *
     * @return string
     */
    public function sanitize($name)
    {
        $name = is_scalar($name) ? trim(strip_tags((string) $name)) : '';

        return mb_substr($name, 0, self::MAX_LENGTH);
    }
}
