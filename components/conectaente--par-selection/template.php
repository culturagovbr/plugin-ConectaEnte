<?php
/**
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

$this->import('
    conectaente--federative-entity-par
    mc-alert
    mc-loading
');
?>
<div class="conectaente-par-selection" :class="classes">
    <mc-loading v-if="loading" :condition="true"></mc-loading>
    <mc-alert v-else-if="!available" type="warning">{{ text('unavailable') }}</mc-alert>
    <conectaente--federative-entity-par
        v-else
        :exercises="exercises"
        :model-value="selection"
        :server-errors="entity.__validationErrors"
        @update:model-value="updateSelection"
    ></conectaente--federative-entity-par>
</div>
