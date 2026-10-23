<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

?>
<div class="conectaente-registration-channels" :class="classes" :data-field="prop">
    <div class="field" :class="{ error: hasError }">
        <label class="field__title">
            {{ text('perguntaPrincipal') }}
            <span class="required">*<?php i::_e('obrigatório') ?></span>
        </label>
        <div class="field__input">
            <select v-model="previstasNoEdital">
                <option value="" disabled>{{ text('selecione') }}</option>
                <option value="sim">{{ text('sim') }}</option>
                <option value="nao">{{ text('nao') }}</option>
            </select>
        </div>
        <small v-if="hasError" class="field__error" role="alert">{{ errorMessage }}</small>
    </div>

    <div v-if="isSim" class="conectaente-registration-channels__detalhamento">
        <h4 class="conectaente-registration-channels__detalhamento-titulo">
            {{ text('detalhamentoTitulo') }}
            <span class="required">*<?php i::_e('obrigatório') ?></span>
        </h4>

        <div class="grid-12">
            <div v-for="channel in channels" :key="channel.value" class="field col-12" :class="{ error: channel.value === emailValue && emailFieldError }">
                <div class="field__group">
                    <label class="field__checkbox">
                        <input type="checkbox" :checked="isTipoMarcado(channel.value)" @change="setMarcado(channel.value, $event.target.checked)">
                        <span>{{ channel.label }}</span>
                    </label>
                </div>
                <template v-if="isTipoMarcado(channel.value)">
                    <label class="field__title">{{ text('descricao') }}</label>
                    <div class="field__input">
                        <input :type="channel.value === emailValue ? 'email' : 'text'" :value="getDescricao(channel.value)" @input="setDescricao(channel.value, $event.target.value)" :placeholder="channel.value === emailValue ? text('descricaoPlaceholderEmail') : text('descricaoPlaceholder')">
                    </div>
                    <small v-if="channel.value === emailValue && emailFieldError" class="field__error" role="alert">{{ emailFieldError }}</small>
                </template>
            </div>
        </div>
    </div>
</div>
