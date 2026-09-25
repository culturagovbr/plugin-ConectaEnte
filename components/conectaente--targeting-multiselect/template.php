<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    entity-field
    mc-multiselect
    mc-tag-list
');
?>
<div v-if="description" class="field conectaente-targeting-multiselect" :class="[{ error: errors.length }, classes]" :data-field="prop">
    <label :id="titleId" class="field__title">
        {{ description.label }}
        <span v-if="required" class="required">*<?php i::_e('obrigatório') ?></span>
    </label>
    <div class="conectaente-targeting-multiselect__row field__input" role="group" :aria-labelledby="titleId">
        <div class="conectaente-targeting-multiselect__checkboxes">
            <label class="conectaente-targeting-multiselect__checkbox">
                <input type="checkbox" :checked="isNotTargeted" @change="onNotTargetedChange">
                <span>{{ description.options[notTargetedKey] }}</span>
            </label>
            <label v-if="!isNotTargeted && allOptions" class="conectaente-targeting-multiselect__checkbox">
                <input type="checkbox" :checked="isAllOptionsSelected" @change="onAllOptionsChange">
                <span>{{ description.options[allOptionsKey] }}</span>
            </label>
        </div>
        <div v-if="!isNotTargeted" class="field__group conectaente-targeting-multiselect__select">
            <mc-multiselect placeholder="<?php i::esc_attr_e('Digite para buscar') ?>" :model="values" :items="selectableOptions" :preserve-order="true" hide-filter hide-button @selected="onSelect" @removed="onRemove"></mc-multiselect>
            <mc-tag-list :tags="selectedTags" :labels="description.options" classes="conectaente-targeting-multiselect__tags" editable @remove="onRemove"></mc-tag-list>
        </div>
        <div v-if="otherProp && isOtherSelected" class="conectaente-targeting-multiselect__other">
            <entity-field :entity="entity" :prop="otherProp" :required="true"></entity-field>
        </div>
    </div>
    <small v-if="errors.length" class="field__error">{{ errors.join('; ') }}</small>
</div>
