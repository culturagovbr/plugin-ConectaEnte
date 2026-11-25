<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    mc-alert
    mc-card
    mc-loading
');
?>
<mc-card class="conectaente-result-mapping">
    <template #title>
        <h3><?php i::_e('Planilha de contemplados') ?></h3>
    </template>
    <template #content>
        <mc-loading :condition="loading"><?php i::_e('Carregando o modelo de planilha') ?></mc-loading>
        <mc-alert v-if="!loading && loadFailed" type="danger" small><?php i::_e('Não foi possível carregar o modelo de planilha agora.') ?></mc-alert>

        <template v-if="!loading && view">
            <mc-alert v-if="!view.available" type="warning" small><?php i::_e('Não foi possível ler o modelo de planilha no CultBR:') ?> {{ view.reason }}</mc-alert>

            <mc-alert v-else-if="!view.hasSpreadsheet" type="helper" small>
                <?php i::_e('Este edital não tem planilha de contemplados no CultBR, e o resultado vai sem ela. Motivo informado pelo CultBR:') ?>
                <strong>{{ view.reason }}</strong>
            </mc-alert>

            <template v-else>
                <p><?php i::_e('Modelo:') ?> <strong>{{ view.model }}</strong>. <?php i::_e('Escolha de qual campo da ficha vem cada coluna.') ?></p>

                <div v-if="hasSuggestion" class="conectaente-result-mapping__suggestion">
                    <mc-alert type="helper" small><?php i::_e('Há colunas com campo sugerido pelo nome. A sugestão só vale depois de confirmada.') ?></mc-alert>
                    <button type="button" class="button button--primary-outline button--sm" @click="confirmSuggestion()"><?php i::_e('Confirmar as sugestões') ?></button>
                </div>

                <div class="conectaente-result-table__scroll">
                    <table class="conectaente-result-table">
                        <thead>
                            <tr>
                                <th><?php i::_e('Coluna do CultBR') ?></th>
                                <th><?php i::_e('Campo da ficha') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="column in view.columns" :key="column.coluna">
                                <td>
                                    <label :for="fieldId('coluna', column.coluna)">{{ column.nome }}</label>
                                    <small v-if="column.obrigatoriedade === 'condicional' && column.condicao" class="field__description conectaente-result-mapping__hint">{{ column.condicao }}</small>
                                </td>
                                <td>
                                    <div class="field" :class="{ error: errors['columns.' + column.coluna] }">
                                        <div class="field__input">
                                            <select :id="fieldId('coluna', column.coluna)" :value="view.mapping.columns[column.coluna] ?? ''" @change="chooseColumn(column, $event)">
                                                <option value=""><?php i::_e('Não se aplica') ?></option>
                                                <optgroup v-for="group in fieldGroups" :key="group.label" :label="group.label">
                                                    <option v-for="field in group.fields" :key="field.id" :value="field.id">{{ field.title }}</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                        <small v-if="suggested(column)" class="field__description"><?php i::_e('Sugestão:') ?> {{ suggested(column).title }}</small>
                                        <small v-if="errors['columns.' + column.coluna]" class="field__error" role="alert">{{ errors['columns.' + column.coluna] }}</small>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h4><?php i::_e('Documento do responsável, por tipo de proponente') ?></h4>
                <div class="conectaente-result-table__scroll">
                    <table class="conectaente-result-table">
                        <thead>
                            <tr>
                                <th><?php i::_e('Tipo de proponente') ?></th>
                                <th><?php i::_e('CPF') ?></th>
                                <th><?php i::_e('CNPJ') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="type in view.proponentTypes" :key="type">
                                <td>{{ type }}</td>
                                <td v-for="kind in documentKinds" :key="kind.key">
                                    <div v-if="kind.key === 'cpf' || hasCnpj(type)" class="field" :class="{ error: errors['documents.' + type + '.' + kind.key] }">
                                        <div class="field__input">
                                            <select :value="view.mapping.documents[type]?.[kind.key] ?? ''" :aria-label="kind.label + ', ' + type" @change="chooseDocument(type, kind.key, $event)">
                                                <option value=""><?php i::_e('Não se aplica') ?></option>
                                                <optgroup v-for="group in fieldGroups" :key="group.label" :label="group.label">
                                                    <option v-for="field in group.fields" :key="field.id" :value="field.id">{{ field.title }}</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                        <small v-if="errors['documents.' + type + '.' + kind.key]" class="field__error" role="alert">{{ errors['documents.' + type + '.' + kind.key] }}</small>
                                    </div>
                                    <span v-else>—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <template v-if="Object.keys(view.pendingEquivalences).length">
                    <h4><?php i::_e('Valores da ficha sem correspondente no CultBR') ?></h4>
                    <div v-for="(values, column) in view.pendingEquivalences" :key="column" class="conectaente-result-mapping__equivalences">
                        <p class="bold">{{ columnByKey(column)?.nome }}</p>
                        <div class="grid-12">
                            <div v-for="value in values" :key="value" class="field col-6 sm:col-12" :class="{ error: errors['equivalences.' + column] }">
                                <label class="field__title" :for="fieldId(column, value)">{{ value }}</label>
                                <div class="field__input">
                                    <select :id="fieldId(column, value)" @change="chooseEquivalence(column, value, $event)">
                                        <option value=""><?php i::_e('Escolha a opção do CultBR') ?></option>
                                        <option v-for="option in columnByKey(column)?.opcoes || []" :key="option" :value="option">{{ option }}</option>
                                    </select>
                                </div>
                                <small v-if="errors['equivalences.' + column]" class="field__error" role="alert">{{ errors['equivalences.' + column] }}</small>
                            </div>
                        </div>
                    </div>
                </template>

                <small v-if="saving" class="field__description"><?php i::_e('Salvando...') ?></small>
            </template>
        </template>
    </template>
</mc-card>
