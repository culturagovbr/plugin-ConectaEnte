<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    conectaente--result-mapping
    conectaente--result-rows
    mc-card
    mc-container
    mc-icon
    mc-loading
    mc-tab
');
?>
<mc-tab label="<?php i::esc_attr_e('Resultado CultBR') ?>" slug="cultbr-resultado">
    <mc-container>
        <main class="conectaente-result-tab">
            <mc-loading :condition="loading"><?php i::_e('Carregando o formulário de resultado') ?></mc-loading>

            <mc-card v-if="!loading && loadFailed">
                <template #content>
                    <p class="conectaente-result-tab__notice"><?php i::_e('Não foi possível carregar o resultado agora. Recarregue a página para tentar de novo.') ?></p>
                </template>
            </mc-card>

            <mc-card v-if="!loading && result && !isOpen">
                <template #title>
                    <h3><?php i::_e('Resultado do edital') ?></h3>
                </template>
                <template #content>
                    <p class="conectaente-result-tab__notice">{{ stateMessage() }}</p>
                </template>
            </mc-card>

            <template v-if="!loading && isOpen">
                <mc-card v-if="result.stale">
                    <template #content>
                        <p class="conectaente-result-tab__notice"><?php i::_e('O formulário do CultBR mudou desde a última gravação. Confira as respostas: as questões novas estão em branco.') ?></p>
                    </template>
                </mc-card>

                <mc-card v-for="step in steps" :key="step.id_etapa">
                    <template #title>
                        <h3>{{ step.nome }}</h3>
                    </template>
                    <template #content>
                        <div class="conectaente-result-tab__questions">
                            <template v-for="question in step.questoes" :key="question.id_questao_etapa">
                                <div v-if="isVisible(question)" :id="'conectaente-result-question-' + question.id_questao_etapa" class="conectaente-result-question" :class="{ 'conectaente-result-question--missing': isMissing(question), 'conectaente-result-question--error': errors[question.id_questao_etapa] }">
                                    <label class="conectaente-result-question__label">
                                        {{ questionLabel(question) }}
                                        <span v-if="question.requerida && isEditable(question)" class="conectaente-result-question__required">*<?php i::_e('obrigatório') ?></span>
                                        <span v-if="isComputed(question)" class="conectaente-result-question__computed"><?php i::_e('calculado pelo Mapas') ?></span>
                                        <span v-else-if="!isEditable(question)" class="conectaente-result-question__computed"><?php i::_e('somente leitura') ?></span>
                                    </label>
                                    <p v-if="question.descricao" class="conectaente-result-question__description">{{ question.descricao }}</p>

                                    <p v-if="!isSupported(question)" class="conectaente-result-question__unsupported">
                                        <?php i::_e('Este tipo de questão ainda não é suportado pelo plugin:') ?> {{ question.tipo_questao }}
                                    </p>

                                    <template v-else-if="!isEditable(question)">
                                        <table v-if="isTable(question)" class="conectaente-result-table">
                                            <thead><tr><th v-for="column in columns(question)" :key="column.key">{{ column.label }}</th></tr></thead>
                                            <tbody>
                                                <tr v-for="(row, index) in (known[question.id_questao_etapa] || [])" :key="index"><td v-for="column in columns(question)" :key="column.key">{{ row[column.key] ?? '—' }}</td></tr>
                                                <tr v-if="!(known[question.id_questao_etapa] || []).length"><td :colspan="columns(question).length"><?php i::_e('Sem linhas a mostrar.') ?></td></tr>
                                            </tbody>
                                        </table>
                                        <p v-else class="conectaente-result-question__value">{{ displayValue(question) }}</p>
                                    </template>

                                    <template v-else-if="question.tipo_questao === 'texto'">
                                        <input type="text" v-model="answers[question.id_questao_etapa]" :maxlength="question.config?.limiteCaracteres || null" :placeholder="question.placeholder || ''">
                                    </template>

                                    <template v-else-if="question.tipo_questao === 'textarea'">
                                        <textarea v-model="answers[question.id_questao_etapa]" :maxlength="question.config?.limiteCaracteres || question.valor_maximo || null" rows="4"></textarea>
                                    </template>

                                    <template v-else-if="question.tipo_questao === 'número' || question.tipo_questao === 'moeda'">
                                        <input type="number" :value="answers[question.id_questao_etapa]" @input="numberValue(question, $event)" :step="question.tipo_questao === 'moeda' ? '0.01' : (question.config?.numericoSomenteInteiros ? '1' : 'any')" :min="question.tipo_questao === 'moeda' ? 0 : (question.config?.numericoMinimo ?? null)">
                                        <p v-if="suggestion(question) !== undefined" class="conectaente-result-question__suggestion">
                                            <?php i::_e('Sugestão do Mapas:') ?> {{ suggestion(question) }}
                                            <button type="button" class="button button--text button--sm" @click="useSuggestion(question)"><?php i::_e('Usar sugestão') ?></button>
                                        </p>
                                    </template>

                                    <template v-else-if="question.tipo_questao === 'data'">
                                        <input type="date" v-model="answers[question.id_questao_etapa]">
                                    </template>

                                    <template v-else-if="question.tipo_questao === 'booleano'">
                                        <div class="conectaente-result-question__options">
                                            <label><input type="radio" :name="'q' + question.id_questao_etapa" :checked="answers[question.id_questao_etapa] === true" @change="answers[question.id_questao_etapa] = true"> {{ question.config?.labelSim || question.opcao || '<?= i::esc_attr__('Sim') ?>' }}</label>
                                            <label><input type="radio" :name="'q' + question.id_questao_etapa" :checked="answers[question.id_questao_etapa] === false" @change="answers[question.id_questao_etapa] = false"> {{ question.config?.labelNao || question.opcao_contraria || '<?= i::esc_attr__('Não') ?>' }}</label>
                                        </div>
                                    </template>

                                    <template v-else-if="question.tipo_questao === 'radio'">
                                        <div class="conectaente-result-question__options">
                                            <template v-for="item in items(question)" :key="item.id">
                                                <label><input type="radio" :name="'q' + question.id_questao_etapa" :checked="selectedId(question) === item.id" @change="selectRadio(question, item)"> {{ item.label }}</label>
                                                <input v-if="complementOf(question, item)" type="text" class="conectaente-result-question__complement" v-model="complementOf(question, item).complemento" :maxlength="item.campoComplementar?.limiteCaracteres || null" :placeholder="item.campoComplementar?.placeholder || item.campoComplementar?.nome || ''">
                                            </template>
                                        </div>
                                    </template>

                                    <template v-else-if="isChoiceList(question)">
                                        <div class="conectaente-result-question__options">
                                            <template v-for="item in items(question)" :key="item.id">
                                                <label><input type="checkbox" :checked="isChecked(question, item)" @change="toggleChoice(question, item)"> {{ item.label }}</label>
                                                <input v-if="complementOf(question, item)" type="text" class="conectaente-result-question__complement" v-model="complementOf(question, item).complemento" :maxlength="item.campoComplementar?.limiteCaracteres || null" :placeholder="item.campoComplementar?.placeholder || item.campoComplementar?.nome || ''">
                                            </template>
                                        </div>
                                    </template>

                                    <template v-else-if="isTable(question)">
                                        <table class="conectaente-result-table">
                                            <thead>
                                                <tr>
                                                    <th v-for="column in columns(question)" :key="column.key">{{ column.label }}</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(row, index) in rows(question)" :key="index">
                                                    <td v-for="column in columns(question)" :key="column.key">
                                                        <select v-if="column.options" v-model="row[column.key]" :disabled="column.readOnly">
                                                            <option value=""></option>
                                                            <option v-for="option in column.options" :key="option" :value="option">{{ option }}</option>
                                                        </select>
                                                        <input v-else :type="column.numeric ? 'number' : 'text'" v-model="row[column.key]" :maxlength="column.max || null" :disabled="column.readOnly">
                                                    </td>
                                                    <td><button type="button" class="button button--text button--sm" @click="removeRow(question, index)"><?php i::_e('Remover') ?></button></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <button type="button" class="button button--primary-outline button--sm" @click="addRow(question)">{{ question.config?.tabela?.textoBotaoAdicionar || question.config?.buttonLabel || '<?= i::esc_attr__('Adicionar linha') ?>' }}</button>
                                    </template>

                                    <template v-else-if="isAttachment(question)">
                                        <p class="conectaente-result-question__description">
                                            <?php i::_e('Arquivo.') ?>
                                            <span v-if="question.config?.anexoModelo?.nome"><?php i::_e('Modelo:') ?> {{ question.config.anexoModelo.nome }}</span>
                                        </p>
                                    </template>

                                    <p v-if="errors[question.id_questao_etapa]" class="conectaente-result-question__error">{{ errors[question.id_questao_etapa] }}</p>
                                </div>
                            </template>
                        </div>
                    </template>
                </mc-card>

                <conectaente--result-mapping :entity="entity"></conectaente--result-mapping>
                <conectaente--result-rows :entity="entity"></conectaente--result-rows>
            </template>
        </main>

        <aside v-if="!loading && isOpen">
            <mc-card>
                <template #title>
                    <h3><?php i::_e('Situação do resultado') ?></h3>
                </template>
                <template #content>
                    <p v-if="result.state === 'complete'" class="conectaente-result-tab__status conectaente-result-tab__status--complete"><?php i::_e('Formulário completo.') ?></p>
                    <template v-else>
                        <p class="conectaente-result-tab__status"><?php i::_e('Faltam estas questões obrigatórias:') ?></p>
                        <ul class="conectaente-result-tab__missing">
                            <li v-for="question in missing" :key="question.id_questao_etapa"><a href="#" @click.prevent="goTo(question)">{{ questionLabel(question) }}</a></li>
                        </ul>
                    </template>
                    <p v-if="saving" class="conectaente-result-tab__saving"><?php i::_e('Salvando...') ?></p>
                    <p v-if="saveFailed" class="conectaente-result-question__error"><?php i::_e('Não foi possível salvar agora. A próxima alteração tenta de novo.') ?></p>
                    <button v-if="result.state === 'complete'" type="button" class="button button--primary conectaente-result-tab__send" disabled><?php i::_e('Enviar ao CultBR') ?></button>
                </template>
            </mc-card>
        </aside>
    </mc-container>
</mc-tab>
