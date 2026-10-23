<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    mc-confirm-button
    mc-currency-input
    mc-icon
');
?>
<div v-if="description" class="conectaente-quota-reservation" :class="[{ 'field--error': hasError }, classes]" :data-field="prop">
    <div class="conectaente-quota-reservation__header field">
        <label class="field__title">
            {{ description.label }}
            <span class="required">*<?php i::_e('obrigatório') ?></span>
        </label>
        <h6>{{ text('sectionDescription') }}</h6>
        <div class="conectaente-quota-reservation__hint">
            <strong>{{ text('infoBlockTitle') }}</strong>
            <ul>
                <li>{{ text('infoBlockItem1') }}</li>
                <li>{{ text('infoBlockItem2') }}</li>
                <li>{{ text('infoBlockItem3') }}</li>
            </ul>
        </div>
    </div>

    <div class="conectaente-quota-reservation__header-row">
        <span class="conectaente-quota-reservation__th">{{ text('descricao') }}</span>
        <span class="conectaente-quota-reservation__th">{{ text('numeroVagas') }}</span>
        <span class="conectaente-quota-reservation__th">{{ text('valorDestinado') }} (R$)</span>
        <span class="conectaente-quota-reservation__th">{{ text('automatico') }}</span>
        <span class="conectaente-quota-reservation__th">{{ text('acoes') }}</span>
    </div>

    <small class="conectaente-quota-reservation__total">
        <span class="conectaente-quota-reservation__total-label">{{ text('total') }}</span>
        <span class="conectaente-quota-reservation__total-vagas">{{ totalVagas }}</span>
        <span class="conectaente-quota-reservation__total-valor">{{ totalAllocatedValueFormatted }}</span>
        <span class="conectaente-quota-reservation__total-automatico">{{ totalPercentFormatted }}</span>
        <span class="conectaente-quota-reservation__total-acoes"></span>
    </small>

    <div class="conectaente-quota-reservation__content" v-for="(quota, index) in quotas" :key="index" :data-field-identifier="getFieldIdentifier(index)" :class="{ 'conectaente-quota-reservation__content--lei': isLawQuota(index), 'conectaente-quota-reservation__content--ampla': isGeneralCompetition(index), 'conectaente-quota-reservation__content--extra': isExtraQuota(index), 'conectaente-quota-reservation__content--error': hasErrorForIndex(index) }">
        <div class="field">
            <input v-if="isFixedQuota(index)" class="field__input" type="text" :value="displayLabel(quota)" readonly disabled>
            <input v-else class="field__input" type="text" v-model.trim="quota.label" :placeholder="text('descricao')" @blur="onBlurField(index)">
        </div>
        <div class="field">
            <input class="field__input" type="number" min="0" step="1" v-model.number="quota.vagas" :disabled="isFixedQuota(index) && quota.naoAplicavel" @blur="onBlurField(index)">
        </div>
        <div class="field">
            <mc-currency-input class="field__input" :key="`valor-${index}-${quota.naoAplicavel}-${quota.valorDestinado}`" v-model.lazy="quota.valorDestinado" :disabled="isFixedQuota(index) && quota.naoAplicavel" @blur="onBlurField(index)"></mc-currency-input>
        </div>
        <div class="field conectaente-quota-reservation__cell-automatico">
            <span class="conectaente-quota-reservation__percentual" :aria-label="text('automatico')">{{ quotaPercent(quota) }}</span>
        </div>
        <div class="field conectaente-quota-reservation__cell-checkbox">
            <label v-if="isFixedQuota(index)" class="field__group field__checkbox">
                <input type="checkbox" v-model="quota.naoAplicavel" @change="onNotApplicableChange(quota)">
                <span>{{ text('naoAplicavel') }}</span>
            </label>
            <span v-else-if="isPendingNewQuota(index)" class="conectaente-quota-reservation__actions-pending">
                <button type="button" class="conectaente-quota-reservation__confirm" @click="confirmNewQuota()" :aria-label="text('confirmarCota')">
                    <mc-icon name="check" class="success__color"></mc-icon>
                    <span>{{ text('confirmarCota') }}</span>
                </button>
                <button type="button" class="conectaente-quota-reservation__delete" @click="cancelNewQuota()" :aria-label="text('cancelarAdicaoCota')">
                    <mc-icon name="trash" class="danger__color"></mc-icon>
                    <span>{{ text('cancelarAdicaoCota') }}</span>
                </button>
            </span>
            <mc-confirm-button v-else @confirm="removeQuota(index)">
                <template #button="{open}">
                    <button type="button" class="conectaente-quota-reservation__delete" @click="open()" :aria-label="text('excluirCota')">
                        <mc-icon name="trash" class="danger__color"></mc-icon>
                        <span>{{ text('excluirCota') }}</span>
                    </button>
                </template>
                <template #message>
                    {{ text('confirmExcluirCota') }}
                </template>
            </mc-confirm-button>
        </div>
    </div>

    <div class="conectaente-quota-reservation__add">
        <button type="button" class="button button--primary button--icon conectaente-quota-reservation__add-btn" @click="addQuota" :disabled="pendingNewQuotaIndex !== null">
            <mc-icon name="add"></mc-icon>
            <span>{{ text('adicionarCota') }}</span>
        </button>
    </div>

    <small v-if="hasError" class="field__error" role="alert">{{ errorMessage }}</small>
</div>
