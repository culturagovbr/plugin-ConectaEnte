<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 * Estrutura alinhada ao entity-field e _field.scss do tema Mapas.
 */

use MapasCulturais\i;

?>
<div class="conectaente-funding-sources" :class="classes" :data-field="prop">
    <!-- Pergunta principal: mesmo padrão do entity-field -->
    <div class="field col-12" :class="{ error: hasError }">
        <label class="field__title">
            {{ text('perguntaPrincipal') }}
            <span class="required">*<?php i::_e('obrigatório') ?></span>
        </label>
        <div class="field__input">
            <select v-model="houveUtilizacao">
                <option value="" disabled>{{ text('selecione') }}</option>
                <option value="sim">{{ text('sim') }}</option>
                <option value="nao">{{ text('nao') }}</option>
            </select>
        </div>
    </div>

    <template v-if="isSim">
        <div class="conectaente-funding-sources__detalhamento col-12">
            <h4 class="conectaente-funding-sources__detalhamento-titulo">
                {{ text('detalhamentoTitulo') }}
                <span class="required">*<?php i::_e('obrigatório') ?></span>
            </h4>

            <div class="grid-12">
                <!-- 1. Recursos próprios -->
                <div class="field col-12">
                    <div class="field__group">
                        <label class="field__checkbox">
                            <input type="checkbox" v-model="recursosPropriosChecked" />
                            <span>{{ sourceLabel('recursosProprios') }}</span>
                        </label>
                    </div>
                    <template v-if="recursosPropriosChecked">
                        <label class="field__title">{{ text('valorRecurso') }} (R$)</label>
                        <div class="field__input">
                            <mc-currency-input :model-value="data.recursosProprios ?? 0" @update:model-value="onCurrencyChange($event, 'recursosProprios')" />
                        </div>
                    </template>
                </div>

                <!-- 2. Convênios/parcerias -->
                <div class="field col-12">
                    <div class="field__group">
                        <label class="field__checkbox">
                            <input type="checkbox" v-model="conveniosParceriasChecked" />
                            <span>{{ sourceLabel('conveniosParcerias') }}</span>
                        </label>
                    </div>
                    <template v-if="conveniosParceriasChecked">
                        <label class="field__title">{{ text('valorRecurso') }} (R$)</label>
                        <div class="field__input">
                            <mc-currency-input :model-value="data.conveniosParcerias ?? 0" @update:model-value="onCurrencyChange($event, 'conveniosParcerias')" />
                        </div>
                    </template>
                </div>

                <!-- 3. Emendas parlamentares -->
                <div class="field col-12">
                    <div class="field__group">
                        <label class="field__checkbox">
                            <input type="checkbox" v-model="emendasParlamentaresChecked" />
                            <span>{{ sourceLabel('emendasParlamentares') }}</span>
                        </label>
                    </div>
                    <template v-if="emendasParlamentaresChecked">
                        <label class="field__title">{{ text('valorRecurso') }} (R$)</label>
                        <div class="field__input">
                            <mc-currency-input :model-value="data.emendasParlamentares ?? 0" @update:model-value="onCurrencyChange($event, 'emendasParlamentares')" />
                        </div>
                    </template>
                </div>

                <!-- 4. Recursos remanescentes do ciclo 1 -->
                <div class="field col-12">
                    <div class="field__group">
                        <label class="field__checkbox">
                            <input type="checkbox" v-model="remanescentesCiclo1Checked" />
                            <span>{{ sourceLabel('remanescentesCiclo1') }}</span>
                        </label>
                    </div>
                    <template v-if="remanescentesCiclo1Checked">
                        <label class="field__title">{{ text('valorRecurso') }} (R$)</label>
                        <div class="field__input">
                            <mc-currency-input :model-value="data.remanescentesCiclo1 ?? 0" @update:model-value="onCurrencyChange($event, 'remanescentesCiclo1')" />
                        </div>
                    </template>
                </div>

                <!-- 5. Recursos de outras fontes (lista dinâmica) -->
                <div class="field col-12">
                    <div class="field__group">
                        <label class="field__checkbox">
                            <input type="checkbox" v-model="outrasFontesChecked" />
                            <span>{{ sourceLabel('outrasFontes') }}</span>
                        </label>
                    </div>
                    <template v-if="outrasFontesChecked">
                        <div v-for="(entrada, index) in outrasFontesList" :key="entrada._id || index" class="field col-12 grid-12" role="group" :aria-labelledby="'outras-fontes-titulo-' + (entrada._id || index)">
                            <div class="col-12">
                                <div class="field__group" style="flex-direction: row; justify-content: space-between; align-items: center;">
                                    <span :id="'outras-fontes-titulo-' + (entrada._id || index)" class="field__title">{{ text('fonteRecurso') }} {{ index + 1 }}</span>
                                    <button type="button" class="button button--danger button--sm" @click="removerOutraFonte(index)" :aria-label="text('removerFonte') + ' ' + (index + 1)">
                                        <?= i::__('Excluir') ?>
                                    </button>
                                </div>
                                <div class="grid-12">
                                    <div class="field col-8 sm:col-12">
                                        <label class="field__title" :for="'outras-fontes-nome-' + (entrada._id || index)">{{ text('nomeFonte') }}</label>
                                        <div class="field__input">
                                            <input type="text" :id="'outras-fontes-nome-' + (entrada._id || index)" v-model="entrada.nomeFonte" :placeholder="text('nomeFontePlaceholder')" :maxlength="nameMaxLength">
                                        </div>
                                    </div>
                                    <div class="field col-4 sm:col-12">
                                        <label class="field__title" :for="'outras-fontes-valor-' + (entrada._id || index)">{{ text('valorRecurso') }} (R$)</label>
                                        <div class="field__input">
                                            <mc-currency-input :id="'outras-fontes-valor-' + (entrada._id || index)" :model-value="entrada.valor ?? 0" @update:model-value="onOutraFonteCurrencyChange(index, $event)" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <p v-if="!podeIncluirOutraFonte" class="field__error">{{ text('preenchaNomesParaIncluir') }}</p>
                            <button type="button" class="button button--primary-outline" @click="incluirOutraFonte" :disabled="!podeIncluirOutraFonte">
                                {{ text('incluir') }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
    <small v-if="hasError" class="field__error" role="alert">{{ errorMessage }}</small>
</div>
