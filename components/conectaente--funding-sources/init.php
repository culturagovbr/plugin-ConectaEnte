<?php
/**
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use ConectaEnte\Services\FundingSourceName;
use ConectaEnte\Vocabulary\FundingSource;

$this->jsObject['config']['conectaenteFundingSources'] = [
    'nameMaxLength' => FundingSourceName::MAX_LENGTH,
    'sources' => array_map(fn(FundingSource $source) => [
        'value' => $source->value,
        'label' => $source->label(),
    ], FundingSource::cases()),
];
