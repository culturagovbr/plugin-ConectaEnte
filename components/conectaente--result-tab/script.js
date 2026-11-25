// escopo próprio: o componente roda ao lado dos do tema, que declaram nomes no global
(() => {
    const AUTOSAVE_DELAY = 1500;
    const SENDING_POLL_INTERVAL = 5000;
    const OPEN_STATES = ['pending', 'complete', 'sent', 'sending', 'uncertain'];
    const CHOICE_LIST_TYPES = ['múltipla escolha', 'checkbox'];
    const TABLE_TYPES = ['tabela', 'Tabela Dinâmica'];
    const ATTACHMENT_TYPES = ['anexo', 'múltiplos anexos'];
    const DEFAULT_MAX_FILE_SIZE = 10;
    const REASONS_WITH_TEXT = ['unreachable', 'rejected'];
    const CELL_MASKS = { cpf: '###.###.###-##', cnpj: '##.###.###/####-##', cep: '#####-###' };
    const SUPPORTED_TYPES = ['texto', 'textarea', 'número', 'moeda', 'data', 'booleano', 'radio', ...CHOICE_LIST_TYPES, ...TABLE_TYPES, ...ATTACHMENT_TYPES];

    // linha de tabela não tem id: a chave acompanha o objeto enquanto ele existe
    const rowKeys = new WeakMap();
    let lastRowKey = 0;

    function choiceId(choice) {
        return choice && typeof choice === 'object' ? choice.id : choice;
    }

    app.component('conectaente--result-tab', {
        template: $TEMPLATES['conectaente--result-tab'],

        props: {
            entity: {
                type: Entity,
                required: true,
            },
        },

        setup() {
            const api = new API('conectaente');
            const text = Utils.getTexts('conectaente--result-tab');
            const messages = useMessages();

            return { api, text, messages };
        },

        data() {
            return {
                result: null,
                answers: {},
                errors: {},
                loading: true,
                loadFailed: false,
                saving: false,
                saveAgain: false,
                saveTimer: null,
                saveFailed: false,
                requestingSend: false,
                sendRefusal: null,
                pollTimer: null,
                uploading: {},
                currencyVersions: {},
                suggestedCommittee: {},
                touchedComplements: {},
                mappingVersion: 0,
            };
        },

        computed: {
            isOpen() {
                return OPEN_STATES.includes(this.result?.state);
            },

            steps() {
                return (this.result?.structure?.secoes || []).flatMap((section) => section.etapas || []);
            },

            questionsById() {
                return Object.fromEntries(this.steps.flatMap((step) => step.questoes || []).map((question) => [question.id_questao_etapa, question]));
            },

            known() {
                return { ...(this.result?.computed || {}), ...this.answers };
            },

            isSending() {
                return this.requestingSend || this.result?.state === 'sending';
            },

            sendLabel() {
                if (this.isSending) {
                    return this.text('em_andamento');
                }

                return this.result?.published ? this.text('retificar') : this.text('enviar');
            },

            refusedQuestions() {
                return this.result?.lastSend?.outcome?.refusedQuestions || {};
            },

            missing() {
                return (this.result?.missing || []).map((id) => this.questionsById[id]).filter(Boolean);
            },
        },

        watch: {
            'result.state'(state, previous) {
                if (previous === 'sending') {
                    this.announceOutcome();
                }

                clearInterval(this.pollTimer);
                this.pollTimer = state === 'sending' ? setInterval(() => this.refresh(), SENDING_POLL_INTERVAL) : null;
            },

            answers: {
                deep: true,
                handler() {
                    if (this.loading) {
                        return;
                    }

                    clearTimeout(this.saveTimer);
                    this.saveTimer = setTimeout(() => this.save(), AUTOSAVE_DELAY);
                },
            },
        },

        mounted() {
            this.load();
        },

        unmounted() {
            clearTimeout(this.saveTimer);
            clearInterval(this.pollTimer);
        },

        methods: {
            async load() {
                this.loading = true;
                this.loadFailed = false;

                try {
                    const response = await this.api.GET(Utils.createUrl('conectaente', 'result', [this.entity.id]), {}, { cache: 'no-store' });

                    if (!response.ok) {
                        throw new Error(response.status);
                    }

                    this.result = await response.json();
                    this.suggestedCommittee = { ...(this.result.committee || {}) };
                    this.answers = JSON.parse(JSON.stringify({ ...(this.result.answers || {}), ...(this.result.committee || {}) }));
                } catch (error) {
                    this.loadFailed = true;
                } finally {
                    this.$nextTick(() => { this.loading = false; });
                }
            },

            // uma gravação por vez: a segunda espera a primeira e leva o formulário como estiver
            async save() {
                if (this.saving) {
                    this.saveAgain = true;
                    return;
                }

                this.saving = true;
                this.saveFailed = false;

                try {
                    const response = await this.api.POST(Utils.createUrl('conectaente', 'resultAnswers', [this.entity.id]), { answers: this.answersToSave() });

                    if (!response.ok) {
                        throw new Error(response.status);
                    }

                    const body = await response.json();
                    this.errors = body.errors || {};
                    this.result = { ...body.result, answers: this.result.answers };
                } catch (error) {
                    this.saveFailed = true;
                } finally {
                    this.saving = false;

                    if (this.saveAgain) {
                        this.saveAgain = false;
                        this.save();
                    }
                }
            },

            // a comissão sugerida só é gravada depois de mexerem nela
            answersToSave() {
                for (const [id, rows] of Object.entries(this.suggestedCommittee)) {
                    if (JSON.stringify(rows) !== JSON.stringify(this.answers[id])) {
                        delete this.suggestedCommittee[id];
                    }
                }

                return Object.fromEntries(Object.entries(this.answers).filter(([id]) => !this.suggestedCommittee[id]));
            },

            // só o estado: as respostas em edição ficam como estão
            async refresh() {
                const response = await this.api.GET(Utils.createUrl('conectaente', 'result', [this.entity.id]), {}, { cache: 'no-store' }).catch(() => null);

                if (response?.ok) {
                    this.result = { ...(await response.json()), answers: this.result.answers };
                }
            },

            mappingSaved() {
                this.mappingVersion++;
                this.refresh();
            },

            formatDate(value) {
                const date = new Date(String(value).replace(' ', 'T'));

                return isNaN(date) ? value : date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
            },

            async send() {
                this.requestingSend = true;
                this.sendRefusal = null;

                try {
                    const response = await this.api.POST(Utils.createUrl('conectaente', 'resultSend', [this.entity.id]), {});
                    const body = await response.json().catch(() => null);

                    if (!response.ok) {
                        this.sendRefusal = body?.data?.message || this.text('erro_envio');

                        if (body?.data?.missing) {
                            this.result = { ...this.result, missing: body.data.missing };
                        }

                        return;
                    }

                    this.result = { ...body.result, answers: this.result.answers };
                } catch (error) {
                    this.sendRefusal = this.text('erro_envio');
                } finally {
                    this.requestingSend = false;
                }
            },

            announceOutcome() {
                const lastSend = this.result?.lastSend;

                if (!lastSend) {
                    return;
                }

                const announce = lastSend.status === 'success' || lastSend.status === 'simulated' ? this.messages.success : this.messages.error;
                announce(lastSend.outcome?.message || this.text(`situacao_${lastSend.status}`));
            },

            fieldId(question) {
                return `conectaente-result-field-${question.id_questao_etapa}`;
            },

            titleId(question) {
                return `conectaente-result-title-${question.id_questao_etapa}`;
            },

            isGroup(question) {
                return !this.isEditable(question) || this.isTable(question) || this.isChoice(question) || this.isAttachment(question) || question.tipo_questao === 'booleano';
            },

            isChoice(question) {
                return question.tipo_questao === 'radio' || this.isChoiceList(question);
            },

            isCep(question) {
                return question.mascara === 'cep' || question.config?.padraoValidacao === 'cep';
            },

            textLimit(question) {
                return question.config?.limiteCaracteres || question.valor_maximo || null;
            },

            // o erro do complemento vazio pertence ao campo dele, e só depois de a pessoa sair dele
            emptyComplement(question) {
                return this.isChoice(question) ? this.items(question).find((item) => this.complementOf(question, item) && !this.complementOf(question, item).complemento?.trim()) : undefined;
            },

            complementKey(question, item) {
                return `${question.id_questao_etapa}:${item.id}`;
            },

            touchComplement(question, item) {
                this.touchedComplements = { ...this.touchedComplements, [this.complementKey(question, item)]: true };
            },

            hasComplementError(question, item) {
                return !!this.errors[question.id_questao_etapa] && this.emptyComplement(question)?.id === item.id && !!this.touchedComplements[this.complementKey(question, item)];
            },

            errorOf(question) {
                if (this.emptyComplement(question)) {
                    return null;
                }

                return this.errors[question.id_questao_etapa] || this.refusedQuestions[question.id_questao_etapa] || null;
            },

            // na tabela a marca é da tabela: no campo ela pintaria todas as células
            hasFieldError(question) {
                return !!this.errorOf(question) && !this.isTable(question) && !this.isAttachment(question);
            },

            questionLabel(question) {
                return question.config?.textoPergunta || question.nome || '';
            },

            isSupported(question) {
                return SUPPORTED_TYPES.includes(question.tipo_questao);
            },

            isComputed(question) {
                return Object.prototype.hasOwnProperty.call(this.result?.computed || {}, question.id_questao_etapa);
            },

            isEditable(question) {
                return !question.somente_leitura && !this.isComputed(question);
            },

            isAttachment(question) {
                return ATTACHMENT_TYPES.includes(question.tipo_questao);
            },

            isDispensed(question) {
                return (this.result?.dispensed || []).includes(question.id_questao_etapa);
            },

            isFilledByLines(question) {
                return (this.result?.filledByLines || []).includes(question.id_questao_etapa);
            },

            isAttachmentUnavailable(question) {
                return (this.result?.unavailableAttachments || []).includes(question.id_questao_etapa);
            },

            isTable(question) {
                return TABLE_TYPES.includes(question.tipo_questao);
            },

            isChoiceList(question) {
                return CHOICE_LIST_TYPES.includes(question.tipo_questao);
            },

            isMissing(question) {
                return (this.result?.missing || []).includes(question.id_questao_etapa);
            },

            suggestion(question) {
                return this.result?.suggested?.[question.id_questao_etapa];
            },

            isVisible(question) {
                return (question.config?.regras?.itens || [])
                    .filter((rule) => rule.tipo === 'visibilidade')
                    .every((rule) => this.ruleMatches(rule.quando || {}) === (rule.efeito?.mostrar ?? true));
            },

            ruleMatches(condition) {
                const source = this.known[condition.fonteIdQuestaoEtapa];

                if (condition.operador === 'eq') {
                    return choiceId(source) === condition.valor;
                }

                if (condition.operador === 'includes') {
                    return Array.isArray(source) && source.map(choiceId).includes(condition.valor);
                }

                return false;
            },

            displayValue(question) {
                const value = this.result?.mirrored?.[question.id_questao_etapa] ?? this.known[question.id_questao_etapa];

                return value === undefined || value === null ? '—' : value;
            },

            items(question) {
                return question.opcao?.itens || [];
            },

            selectedId(question) {
                return choiceId(this.answers[question.id_questao_etapa]);
            },

            selectRadio(question, item) {
                this.answers[question.id_questao_etapa] = item.tipo === 'campoComplementar' ? { id: item.id, complemento: '' } : item.id;
            },

            isChecked(question, item) {
                return (this.answers[question.id_questao_etapa] || []).map(choiceId).includes(item.id);
            },

            toggleChoice(question, item) {
                const current = this.answers[question.id_questao_etapa] || [];
                const without = current.filter((choice) => choiceId(choice) !== item.id);

                if (without.length < current.length) {
                    this.answers[question.id_questao_etapa] = without;
                    return;
                }

                const choice = item.tipo === 'campoComplementar' ? { id: item.id, complemento: '' } : item.id;
                // opção exclusiva não convive com outra, nos dois sentidos
                const kept = item.exclusiva ? [] : without.filter((other) => !this.items(question).find((candidate) => candidate.id === choiceId(other))?.exclusiva);

                this.answers[question.id_questao_etapa] = [...kept, choice];
            },

            complementOf(question, item) {
                const answer = this.answers[question.id_questao_etapa];
                const choices = Array.isArray(answer) ? answer : [answer];

                return choices.find((choice) => choiceId(choice) === item.id && typeof choice === 'object');
            },

            columns(question) {
                const config = question.config || {};

                return (config.tabela?.colunas || config.columns || []).map((column) => ({
                    key: column.id ?? column.key,
                    label: column.nome ?? column.label,
                    options: column.opcoes || column.dropdownOptions?.values || null,
                    kind: column.tipo ?? column.type ?? 'text',
                    mask: CELL_MASKS[column.id ?? column.key] || null,
                    readOnly: column.somenteLeitura ?? column.readOnly ?? false,
                    max: column.maxCaracteres ?? column.max ?? null,
                }));
            },

            cellText(column, row) {
                const value = row[column.key];

                if (value === undefined || value === null || value === '') {
                    return '—';
                }

                return column.kind === 'currency' ? this.formatCurrency(value) : value;
            },

            rowKey(row, index) {
                if (!row || typeof row !== 'object') {
                    return index;
                }

                if (!rowKeys.has(row)) {
                    rowKeys.set(row, ++lastRowKey);
                }

                return rowKeys.get(row);
            },

            cellId(question, row, index, column) {
                return `${this.fieldId(question)}-${this.rowKey(row, index)}-${column.key}`;
            },

            formatCurrency(value) {
                return Number(value).toLocaleString($MAPAS.config.locale, { style: 'currency', currency: $MAPAS.config.currency });
            },

            rows(question) {
                return this.answers[question.id_questao_etapa] || [];
            },

            addRow(question) {
                const row = Object.fromEntries(this.columns(question).map((column) => [column.key, '']));
                this.answers[question.id_questao_etapa] = [...this.rows(question), row];
            },

            hasValuers(question) {
                return this.rows(question).some((row) => row.agentId);
            },

            isMaskedCell(row, column) {
                return !!row.agentId && String(row[column.key] ?? '').startsWith('***');
            },

            removeRow(question, index) {
                this.answers[question.id_questao_etapa] = this.rows(question).filter((row, rowIndex) => rowIndex !== index);
            },

            numberValue(question, event) {
                const raw = event.target.value;
                this.answers[question.id_questao_etapa] = raw === '' ? null : Number(raw);
            },

            useSuggestion(question) {
                this.answers[question.id_questao_etapa] = this.suggestion(question);
                // o campo de moeda não relê o valor que muda por fora
                this.currencyVersions = { ...this.currencyVersions, [question.id_questao_etapa]: (this.currencyVersions[question.id_questao_etapa] || 0) + 1 };
            },

            maxFileSize(question) {
                return question.config?.tamanhoMaxMb || DEFAULT_MAX_FILE_SIZE;
            },

            attachments(question) {
                const entries = this.answers[question.id_questao_etapa];

                return Array.isArray(entries) ? entries.filter((entry) => entry && entry.fileId) : [];
            },

            acceptsAnotherFile(question) {
                return question.tipo_questao === 'múltiplos anexos' || question.config?.multiplos || !this.attachments(question).length;
            },

            async uploadAttachment(question, event) {
                const file = event.target.files?.[0];

                if (!file) {
                    return;
                }

                event.target.value = '';

                if (file.size > this.maxFileSize(question) * 1024 * 1024) {
                    this.errors = { ...this.errors, [question.id_questao_etapa]: this.text('arquivo_grande', { tamanho: this.maxFileSize(question) }) };
                    return;
                }

                const form = new FormData();
                form.append('questionId', question.id_questao_etapa);
                form.append('arquivo', file);

                this.uploading = { ...this.uploading, [question.id_questao_etapa]: true };
                await this.changeAttachment(question, 'resultAttachment', form);
                this.uploading = { ...this.uploading, [question.id_questao_etapa]: false };
            },

            removeAttachment(question, entry) {
                return this.changeAttachment(question, 'resultAttachmentDelete', { questionId: question.id_questao_etapa, fileId: entry.fileId });
            },

            async changeAttachment(question, action, data) {
                try {
                    const response = await this.api.POST(Utils.createUrl('conectaente', action, [this.entity.id]), data);
                    const body = await response.json().catch(() => null);

                    this.afterAttachmentChange(question, response.ok, body);
                } catch (error) {
                    this.messages.error(this.text('erro_arquivo'));
                }
            },

            afterAttachmentChange(question, ok, body) {
                if (!ok) {
                    this.errors = { ...this.errors, [question.id_questao_etapa]: body?.data?.arquivo?.[0] || this.text('erro_arquivo') };
                    return;
                }

                const { [question.id_questao_etapa]: removed, ...others } = this.errors;
                this.errors = others;
                this.result = body;
                this.answers[question.id_questao_etapa] = body.answers?.[question.id_questao_etapa] ?? null;
            },

            goTo(question) {
                document.getElementById(`conectaente-result-question-${question.id_questao_etapa}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            },

            stateMessage() {
                const state = this.result?.state;

                const reason = this.result?.reason;

                return this.text(`estado_${state}`, { motivo: REASONS_WITH_TEXT.includes(reason) ? this.text(`motivo_${reason}`) : '' });
            },
        },
    });
})();
