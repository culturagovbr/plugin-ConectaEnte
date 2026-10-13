<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

$this->import('mc-icon');
?>
<teleport defer to=".entity-header .title">
    <span v-if="state" class="conectaente-send-status" :class="'conectaente-send-status--' + state" role="status">
        <mc-icon :name="icon"></mc-icon>
        <span>{{ label }}</span>
    </span>
</teleport>
