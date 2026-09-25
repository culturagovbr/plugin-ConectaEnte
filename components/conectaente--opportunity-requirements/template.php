<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    mc-loading
');
?>
<div class="conectaente-opportunity-requirements" :class="{ 'conectaente-opportunity-requirements--loading': loading }" aria-live="polite">
    <p v-if="failed" class="conectaente-opportunity-requirements__failed">
        <?php i::_e('Não foi possível carregar os campos pendentes.') ?>
    </p>
    <mc-loading v-else-if="!missing" :condition="true"></mc-loading>
    <p v-else-if="!fields.length" class="conectaente-opportunity-requirements__done">
        <?php i::_e('Nenhum campo pendente.') ?>
    </p>
    <ul v-else class="conectaente-opportunity-requirements__list">
        <li v-for="field in fields" :key="field.key" class="conectaente-opportunity-requirements__field">
            <strong>{{ field.label }}</strong>
            <ul>
                <li v-for="message in field.messages" :key="message">{{ message }}</li>
            </ul>
        </li>
    </ul>
</div>
