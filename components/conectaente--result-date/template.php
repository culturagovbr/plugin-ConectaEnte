<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */
?>
<div class="entity-field-datepicker">
    <div class="entity-field-datepicker__date">
        <input v-maska data-maska="##/##/####" :id="inputId" class="date-input" type="text" autocomplete="off" :value="typed" @input="type($event)" @blur="leave()">
        <datepicker
            :teleport="true"
            :model-value="picked"
            @update:model-value="pick"
            :dayNames="dayNames"
            :locale="locale"
            :weekStart="0"
            :enable-time-picker="false"
            autoApply>
            <template #trigger>
                <div class="calendar"></div>
            </template>
        </datepicker>
    </div>
</div>
