<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    mc-multiselect
    mc-tag-list
');
?>
<div class="conectaente-affirmative-actions" :class="classes" :data-field="prop">
    <div class="field" :class="{ error: hasError }">
        <label class="field__title">
            {{ description.label }}
            <span class="required">*<?php i::_e('obrigatório') ?></span>
        </label>

        <div class="grid-12 conectaente-affirmative-actions__opcoes">
            <div class="field col-12">
                <div class="field__group">
                    <label class="field__checkbox">
                        <input type="checkbox" :checked="isNaoPrevistasMarcado" @change="setNaoPrevistas($event.target.checked)">
                        <span>{{ notPlanned.label }}</span>
                    </label>
                </div>
            </div>

            <div v-for="option in options" :key="option.value" class="field col-12">
                <div class="field__group">
                    <label class="field__checkbox">
                        <input type="checkbox" :checked="isOpcaoMarcada(option.value)" :disabled="isNaoPrevistasMarcado" @change="setOpcao(option.value, $event.target.checked)">
                        <span>{{ option.label }}</span>
                    </label>
                </div>

                <template v-if="option.hasGroups && isOpcaoMarcada(option.value) && !isNaoPrevistasMarcado">
                    <div class="conectaente-affirmative-actions__sublista field__input" :class="{ error: hasErrorForSublista(option.value) }">
                        <div class="conectaente-affirmative-actions__multiselect-wrap">
                            <mc-multiselect :model="getSublistModel(option.value)" :items="groups" :placeholder="text('subcategoriasPlaceholder')" hide-button :preserve-order="true"></mc-multiselect>
                        </div>
                        <mc-tag-list editable classes="opportunity__background opportunity__color" :tags="getSublistModel(option.value)" :labels="sublistLabels"></mc-tag-list>
                    </div>
                </template>

                <template v-if="option.value === otherLegislation && isOpcaoMarcada(option.value) && !isNaoPrevistasMarcado">
                    <label class="field__title">{{ text('descrevaAcao') }}</label>
                    <div class="field__input conectaente-affirmative-actions__descricao-wrap" :class="{ error: hasErrorOutraLegislacao }">
                        <div class="conectaente-affirmative-actions__input-row">
                            <input type="text" :value="descricaoOutra" @input="setDescricaoOutra($event.target.value)" :placeholder="text('descrevaAcaoPlaceholder')" :maxlength="descriptionMaxLength">
                            <span class="conectaente-affirmative-actions__contador" aria-live="polite">{{ contadorCaracteres }}</span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <small v-for="message in errorMessages" :key="message" class="field__error" role="alert">{{ message }}</small>
    </div>
</div>
