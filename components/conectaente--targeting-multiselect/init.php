<?php
/**
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use ConectaEnte\Vocabulary\TargetingOption;

$this->jsObject['config']['conectaenteTargetingMultiselect'] = [
    'notTargeted' => TargetingOption::NOT_TARGETED->value,
    'allOptions' => TargetingOption::ALL_OPTIONS->value,
];
