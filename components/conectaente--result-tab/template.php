<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    conectaente--result-date
    conectaente--result-mapping
    conectaente--result-rows
    mc-alert
    mc-card
    mc-confirm-button
    mc-container
    mc-currency-input
    mc-icon
    mc-loading
    mc-tab
');
?>
<mc-tab label="<?php i::esc_attr_e('Resultado CultBR') ?>" slug="cultbr-resultado">
    <mc-container>
        <main class="conectaente-result-tab">
            <mc-loading :condition="loading"><?php i::_e('Carregando o formulário de resultado') ?></mc-loading>

            <mc-alert v-if="!loading && loadFailed" type="danger"><?php i::_e('Não foi possível carregar o resultado agora. Recarregue a página para tentar de novo.') ?></mc-alert>

            <mc-card v-if="!loading && result && !isOpen">
                <template #title>
                    <h3><?php i::_e('Resultado do edital') ?></h3>
                </template>
                <template #content>
                    <mc-alert type="helper">{{ stateMessage() }}</mc-alert>
                </template>
            </mc-card>

            <template v-if="!loading && isOpen">
                <mc-alert v-if="result.stale" type="warning"><?php i::_e('O formulário do CultBR mudou desde a última gravação. Confira as respostas: as questões novas estão em branco.') ?></mc-alert>

                <mc-card v-for="step in steps" :key="step.id_etapa">
                    <template #title>
                        <h3>{{ step.nome }}</h3>
                    </template>
                    <template #content>
                        <div class="conectaente-result-tab__questions">
                            <template v-for="question in step.questoes" :key="question.id_questao_etapa">
                                <div v-if="isVisible(question)" :id="'conectaente-result-question-' + question.id_questao_etapa" class="field conectaente-result-question" :class="{ 'conectaente-result-question--missing': isMissing(question), error: hasFieldError(question) }" :role="isGroup(question) ? 'group' : null" :aria-labelledby="isGroup(question) ? titleId(question) : null">
                                    <label class="field__title" :id="titleId(question)" :for="isGroup(question) ? null : fieldId(question)">
                                        {{ questionLabel(question) }}
                                        <span v-if="question.requerida && isEditable(question) && !isDispensed(question)" class="required">*<?php i::_e('obrigatório') ?></span>
                                        <small v-if="isComputed(question)" class="field__description"><?php i::_e('calculado pelo Mapas') ?></small>
                                        <small v-else-if="!isEditable(question)" class="field__description"><?php i::_e('somente leitura') ?></small>
                                    </label>
                                    <small v-if="question.descricao" class="field__description">{{ question.descricao }}</small>

                                    <mc-alert v-if="!isSupported(question)" type="warning" small>
                                        <?php i::_e('Este tipo de questão ainda não é suportado pelo plugin:') ?> {{ question.tipo_questao }}
                                    </mc-alert>

                                    <template v-else-if="!isEditable(question)">
                                        <div v-if="isTable(question)" class="conectaente-result-table__scroll">
                                            <table class="conectaente-result-table">
                                                <thead><tr><th v-for="column in columns(question)" :key="column.key">{{ column.label }}</th></tr></thead>
                                                <tbody>
                                                    <tr v-for="(row, index) in (known[question.id_questao_etapa] || [])" :key="rowKey(row, index)"><td v-for="column in columns(question)" :key="column.key">{{ cellText(column, row) }}</td></tr>
                                                    <tr v-if="!(known[question.id_questao_etapa] || []).length"><td :colspan="columns(question).length"><?php i::_e('Sem linhas a mostrar.') ?></td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <p v-else class="conectaente-result-question__value">{{ displayValue(question) }}</p>
                                    </template>

                                    <div v-else-if="question.tipo_questao === 'texto'" class="field__input">
                                        <input v-if="isCep(question)" v-maska data-maska="#####-###" :id="fieldId(question)" type="text" v-model="answers[question.id_questao_etapa]" :placeholder="question.placeholder || ''" autocomplete="off">
                                        <input v-else :id="fieldId(question)" type="text" v-model="answers[question.id_questao_etapa]" :maxlength="question.config?.limiteCaracteres || null" :placeholder="question.placeholder || ''" autocomplete="off">
                                    </div>

                                    <div v-else-if="question.tipo_questao === 'textarea'" class="field__input">
                                        <textarea :id="fieldId(question)" v-model="answers[question.id_questao_etapa]" :maxlength="textLimit(question)"></textarea>
                                        <span v-if="textLimit(question)" class="field__length">{{ (answers[question.id_questao_etapa] || '').length }}/{{ textLimit(question) }}</span>
                                    </div>

                                    <template v-else-if="question.tipo_questao === 'moeda'">
                                        <div class="field__input">
                                            <mc-currency-input :key="'moeda-' + (currencyVersions[question.id_questao_etapa] || 0)" :id="fieldId(question)" v-model.lazy="answers[question.id_questao_etapa]"></mc-currency-input>
                                        </div>
                                        <small v-if="suggestion(question) !== undefined" class="field__description conectaente-result-question__suggestion">
                                            <?php i::_e('Sugestão do Mapas:') ?> {{ formatCurrency(suggestion(question)) }}
                                            <button type="button" class="button button--text button--sm" @click="useSuggestion(question)"><?php i::_e('Usar sugestão') ?></button>
                                        </small>
                                    </template>

                                    <div v-else-if="question.tipo_questao === 'número'" class="field__input">
                                        <input :id="fieldId(question)" type="number" :value="answers[question.id_questao_etapa]" @input="numberValue(question, $event)" :step="question.config?.numericoSomenteInteiros ? '1' : 'any'" :min="question.config?.numericoMinimo ?? null">
                                    </div>

                                    <div v-else-if="question.tipo_questao === 'data'" class="field__input">
                                        <conectaente--result-date :input-id="fieldId(question)" v-model="answers[question.id_questao_etapa]"></conectaente--result-date>
                                    </div>

                                    <div v-else-if="question.tipo_questao === 'booleano'" class="field__group" role="radiogroup" :aria-labelledby="titleId(question)">
                                        <label class="input__label input__radioLabel"><input type="radio" :name="'q' + question.id_questao_etapa" :checked="answers[question.id_questao_etapa] === true" @change="answers[question.id_questao_etapa] = true"> {{ question.config?.labelSim || question.opcao || '<?= i::esc_attr__('Sim') ?>' }}</label>
                                        <label class="input__label input__radioLabel"><input type="radio" :name="'q' + question.id_questao_etapa" :checked="answers[question.id_questao_etapa] === false" @change="answers[question.id_questao_etapa] = false"> {{ question.config?.labelNao || question.opcao_contraria || '<?= i::esc_attr__('Não') ?>' }}</label>
                                    </div>

                                    <div v-else-if="isChoice(question)" class="field__group">
                                        <template v-for="item in items(question)" :key="item.id">
                                            <label v-if="question.tipo_questao === 'radio'" class="input__label input__radioLabel"><input type="radio" :name="'q' + question.id_questao_etapa" :checked="selectedId(question) === item.id" @change="selectRadio(question, item)"> {{ item.label }}</label>
                                            <label v-else class="input__label input__checkboxLabel"><input type="checkbox" :checked="isChecked(question, item)" @change="toggleChoice(question, item)"> {{ item.label }}</label>

                                            <div v-if="complementOf(question, item)" class="field conectaente-result-question__complement" :class="{ error: hasComplementError(question, item) }">
                                                <label class="field__title" :for="fieldId(question) + '-' + item.id">
                                                    {{ item.campoComplementar?.nome || item.label }}
                                                    <span class="required">*<?php i::_e('obrigatório') ?></span>
                                                </label>
                                                <div class="field__input">
                                                    <input :id="fieldId(question) + '-' + item.id" type="text" v-model="complementOf(question, item).complemento" :maxlength="item.campoComplementar?.limiteCaracteres || null" :placeholder="item.campoComplementar?.placeholder || ''" @blur="touchComplement(question, item)">
                                                </div>
                                                <small v-if="hasComplementError(question, item)" class="field__error" role="alert">{{ errors[question.id_questao_etapa] }}</small>
                                            </div>
                                        </template>
                                    </div>

                                    <template v-else-if="isTable(question)">
                                        <div v-for="(row, index) in rows(question)" :key="rowKey(row, index)" class="conectaente-result-row" :class="{ 'conectaente-result-row--error': errorOf(question) }">
                                            <div class="grid-12">
                                                <div v-for="column in columns(question)" :key="column.key" class="field col-6 sm:col-12">
                                                    <label class="field__title" :for="cellId(question, row, index, column)">{{ column.label }}</label>
                                                    <div class="field__input">
                                                        <select v-if="column.options" :id="cellId(question, row, index, column)" v-model="row[column.key]" :disabled="column.readOnly">
                                                            <option value=""><?php i::_e('Selecione') ?></option>
                                                            <option v-for="option in column.options" :key="option" :value="option">{{ option }}</option>
                                                        </select>
                                                        <mc-currency-input v-else-if="column.kind === 'currency'" :id="cellId(question, row, index, column)" v-model.lazy="row[column.key]" :disabled="column.readOnly"></mc-currency-input>
                                                        <input v-else-if="column.mask" v-maska :data-maska="column.mask" :id="cellId(question, row, index, column)" type="text" v-model="row[column.key]" :disabled="column.readOnly" autocomplete="off">
                                                        <input v-else-if="column.kind === 'number'" :id="cellId(question, row, index, column)" type="number" v-model.number="row[column.key]" :disabled="column.readOnly">
                                                        <input v-else :id="cellId(question, row, index, column)" type="text" v-model="row[column.key]" :maxlength="column.max || null" :disabled="column.readOnly" autocomplete="off">
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button" class="button button--text button--sm conectaente-result-row__remove" @click="removeRow(question, index)">
                                                <mc-icon name="trash" class="danger__color"></mc-icon>
                                                <?php i::_e('Remover') ?>
                                            </button>
                                        </div>
                                        <button type="button" class="button button--primary-outline button--sm button--icon conectaente-result-question__add" @click="addRow(question)">
                                            <mc-icon name="add"></mc-icon>
                                            {{ question.config?.tabela?.textoBotaoAdicionar || question.config?.buttonLabel || '<?= i::esc_attr__('Adicionar linha') ?>' }}
                                        </button>
                                    </template>

                                    <template v-else-if="isAttachment(question)">
                                        <small v-if="isFilledByLines(question)" class="field__description"><?php i::_e('O CultBR preenche esta questão com a grade de contemplados.') ?></small>
                                        <small v-else-if="isAttachmentUnavailable(question)" class="field__description"><?php i::_e('Este edital envia a planilha de contemplados, e o CultBR não recebe arquivos junto com ela.') ?></small>
                                        <small v-else class="field__description">
                                            <span v-if="question.config?.anexoModelo?.nome"><?php i::_e('Modelo:') ?> {{ question.config.anexoModelo.nome }}. </span>
                                            <span v-if="question.config?.formatos?.length"><?php i::_e('Formatos:') ?> {{ question.config.formatos.join(', ') }}. </span>
                                            <?php i::_e('Tamanho máximo do arquivo:') ?> {{ maxFileSize(question) }} MB.
                                            <?php i::_e('O arquivo vai ao CultBR junto com o resultado.') ?>
                                        </small>

                                        <div v-for="entry in attachments(question)" :key="entry.fileId" class="entity-file__file conectaente-result-question__file">
                                            <span class="bold">{{ entry.name }}</span>
                                            <mc-confirm-button @confirm="removeAttachment(question, entry)">
                                                <template #button="modal">
                                                    <button type="button" class="button button--text button--sm" :aria-label="text('remover_arquivo', { nome: entry.name })" @click="modal.open()"><mc-icon name="trash" class="danger__color"></mc-icon></button>
                                                </template>
                                                <template #message>
                                                    <?php i::_e('Deseja remover este arquivo?') ?>
                                                </template>
                                            </mc-confirm-button>
                                        </div>

                                        <mc-loading :condition="uploading[question.id_questao_etapa] || false"><?php i::_e('Enviando o arquivo') ?></mc-loading>
                                        <label v-if="!uploading[question.id_questao_etapa] && !isAttachmentUnavailable(question) && acceptsAnotherFile(question)" class="button button--icon button--primary-outline conectaente-result-question__upload">
                                            <mc-icon name="upload"></mc-icon>
                                            <?php i::_e('Escolher arquivo') ?>
                                            <input :id="fieldId(question)" type="file" class="conectaente-result-question__upload-input" :accept="(question.config?.formatos || []).join(',')" :aria-label="questionLabel(question)" @change="uploadAttachment(question, $event)">
                                        </label>
                                    </template>

                                    <small v-if="errorOf(question)" class="field__error" role="alert">{{ errorOf(question) }}</small>
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
                    <p v-if="result.state === 'sending'" class="conectaente-result-badge conectaente-result-badge--pending" role="status"><?php i::_e('Envio ao CultBR em andamento.') ?></p>
                    <mc-alert v-else-if="result.state === 'uncertain'" type="warning" small><?php i::_e('O último envio pode ter chegado ao CultBR. Confira lá antes de enviar de novo.') ?></mc-alert>
                    <p v-else-if="result.state === 'complete' || result.state === 'sent'" class="conectaente-result-badge conectaente-result-badge--success" role="status"><mc-icon name="circle-checked"></mc-icon> <?php i::_e('Formulário completo.') ?></p>
                    <template v-else-if="missing.length">
                        <mc-alert type="warning" small><?php i::_e('Faltam estas questões obrigatórias:') ?></mc-alert>
                        <ul class="conectaente-result-tab__missing">
                            <li v-for="question in missing" :key="question.id_questao_etapa"><a href="#" @click.prevent="goTo(question)">{{ questionLabel(question) }}</a></li>
                        </ul>
                    </template>
                    <mc-alert v-if="result.spreadsheetPending" type="warning" small>{{ text('grade_com_pendencia', { total: result.spreadsheetPending }) }}</mc-alert>

                    <section v-if="result.lastSend" class="conectaente-result-tab__last-send" :class="'conectaente-result-tab__last-send--' + result.lastSend.status">
                        <h4><?php i::_e('Último envio') ?></h4>
                        <p>
                            <strong>{{ text('situacao_' + result.lastSend.status) }}</strong>
                            <small v-if="result.lastSend.at"> {{ formatDate(result.lastSend.at) }}</small>
                        </p>
                        <p>{{ result.lastSend.outcome?.message }}</p>
                        <ul v-if="result.lastSend.outcome?.items?.length" class="conectaente-result-tab__refusals">
                            <li v-for="item in result.lastSend.outcome.items" :key="item">{{ item }}</li>
                        </ul>
                        <p v-if="result.lastSend.outcome?.hiddenRows">{{ text('linhas_ocultas', { total: result.lastSend.outcome.hiddenRows }) }}</p>
                        <p v-if="result.lastSend.resultId" class="conectaente-result-tab__current">{{ text('resultado_vigente', { id: result.lastSend.resultId, tipo: text('tipo_' + result.lastSend.type) }) }}</p>
                    </section>

                    <small v-if="saving" class="field__description"><?php i::_e('Salvando...') ?></small>
                    <mc-alert v-if="saveFailed" type="danger" small><?php i::_e('Não foi possível salvar agora. A próxima alteração tenta de novo.') ?></mc-alert>
                    <mc-alert v-if="sendRefusal" type="danger" small>{{ sendRefusal }}</mc-alert>

                    <button v-if="result.sendButton" type="button" class="button button--primary button--large conectaente-result-tab__send" :disabled="requestingSend || saving" @click="send()">
                        {{ result.published ? text('retificar') : text('enviar') }}
                    </button>
                </template>
            </mc-card>
        </aside>
    </mc-container>
</mc-tab>
