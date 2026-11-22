<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
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

        <p v-if="!loading && loadFailed"><?php i::_e('Não foi possível carregar o modelo de planilha agora.') ?></p>

        <template v-if="!loading && view">
            <p v-if="!view.available"><?php i::_e('Não foi possível ler o modelo de planilha no CultBR:') ?> {{ view.reason }}</p>

            <p v-else-if="!view.hasSpreadsheet">
                <?php i::_e('Este edital não tem planilha de contemplados no CultBR, e o resultado vai sem ela. Motivo informado pelo CultBR:') ?>
                <strong>{{ view.reason }}</strong>
            </p>

            <template v-else>
                <p><?php i::_e('Modelo:') ?> <strong>{{ view.model }}</strong>. <?php i::_e('Escolha de qual campo da ficha vem cada coluna.') ?></p>

                <div v-if="hasSuggestion" class="conectaente-result-mapping__suggestion">
                    <p><?php i::_e('Há colunas com campo sugerido pelo nome. A sugestão só vale depois de confirmada.') ?></p>
                    <button type="button" class="button button--primary-outline button--sm" @click="confirmSuggestion()"><?php i::_e('Confirmar as sugestões') ?></button>
                </div>

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
                                {{ column.nome }}
                                <small v-if="column.obrigatoriedade === 'condicional' && column.condicao" class="conectaente-result-mapping__hint">{{ column.condicao }}</small>
                            </td>
                            <td>
                                <select :value="view.mapping.columns[column.coluna] ?? ''" @change="chooseColumn(column, $event)">
                                    <option value=""><?php i::_e('Não se aplica') ?></option>
                                    <optgroup v-for="(fields, group) in groupsFor(view.fieldsByColumn[column.coluna] || [])" :key="group" :label="group">
                                        <option v-for="field in fields" :key="field.id" :value="field.id">{{ field.title }}</option>
                                    </optgroup>
                                </select>
                                <small v-if="suggested(column)" class="conectaente-result-mapping__hint"><?php i::_e('Sugestão:') ?> {{ suggested(column).title }}</small>
                                <small v-if="errors['columns.' + column.coluna]" class="conectaente-result-question__error">{{ errors['columns.' + column.coluna] }}</small>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h4><?php i::_e('Documento do responsável, por tipo de proponente') ?></h4>
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
                            <td>
                                <select :value="view.mapping.documents[type]?.cpf ?? ''" @change="chooseDocument(type, 'cpf', $event)">
                                    <option value=""><?php i::_e('Não se aplica') ?></option>
                                    <optgroup v-for="(fields, group) in groupsFor(allFieldIds())" :key="group" :label="group">
                                        <option v-for="field in fields" :key="field.id" :value="field.id">{{ field.title }}</option>
                                    </optgroup>
                                </select>
                            </td>
                            <td>
                                <select v-if="hasCnpj(type)" :value="view.mapping.documents[type]?.cnpj ?? ''" @change="chooseDocument(type, 'cnpj', $event)">
                                    <option value=""><?php i::_e('Não se aplica') ?></option>
                                    <optgroup v-for="(fields, group) in groupsFor(allFieldIds())" :key="group" :label="group">
                                        <option v-for="field in fields" :key="field.id" :value="field.id">{{ field.title }}</option>
                                    </optgroup>
                                </select>
                                <span v-else>—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <template v-if="Object.keys(view.pendingEquivalences).length">
                    <h4><?php i::_e('Valores da ficha sem correspondente no CultBR') ?></h4>
                    <div v-for="(values, column) in view.pendingEquivalences" :key="column" class="conectaente-result-mapping__equivalence">
                        <p><strong>{{ columnByKey(column)?.nome }}</strong></p>
                        <label v-for="value in values" :key="value">
                            {{ value }}
                            <select @change="chooseEquivalence(column, value, $event)">
                                <option value=""><?php i::_e('Escolha a opção do CultBR') ?></option>
                                <option v-for="option in columnByKey(column)?.opcoes || []" :key="option" :value="option">{{ option }}</option>
                            </select>
                        </label>
                    </div>
                </template>

                <p v-if="saving" class="conectaente-result-tab__saving"><?php i::_e('Salvando...') ?></p>
                <p v-if="errors.geral" class="conectaente-result-question__error">{{ errors.geral }}</p>
            </template>
        </template>
    </template>
</mc-card>
