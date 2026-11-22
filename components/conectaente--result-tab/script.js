// escopo próprio: o componente roda ao lado dos do tema, que declaram nomes no global
(() => {
    const AUTOSAVE_DELAY = 1500;
    const OPEN_STATES = ['pending', 'complete'];
    const CHOICE_LIST_TYPES = ['múltipla escolha', 'checkbox'];
    const TABLE_TYPES = ['tabela', 'Tabela Dinâmica'];
    const ATTACHMENT_TYPES = ['anexo', 'múltiplos anexos'];
    const SUPPORTED_TYPES = ['texto', 'textarea', 'número', 'moeda', 'data', 'booleano', 'radio', ...CHOICE_LIST_TYPES, ...TABLE_TYPES, ...ATTACHMENT_TYPES];

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

            return { api, text };
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

            missing() {
                return (this.result?.missing || []).map((id) => this.questionsById[id]).filter(Boolean);
            },
        },

        watch: {
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
                    this.answers = JSON.parse(JSON.stringify(this.result.answers || {}));
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
                    const response = await this.api.POST(Utils.createUrl('conectaente', 'resultAnswers', [this.entity.id]), { answers: this.answers });

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
                const value = this.known[question.id_questao_etapa];

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
                    numeric: ['number', 'currency'].includes(column.tipo ?? column.type),
                    readOnly: column.somenteLeitura ?? column.readOnly ?? false,
                    max: column.maxCaracteres ?? column.max ?? null,
                }));
            },

            rows(question) {
                return this.answers[question.id_questao_etapa] || [];
            },

            addRow(question) {
                const row = Object.fromEntries(this.columns(question).map((column) => [column.key, '']));
                this.answers[question.id_questao_etapa] = [...this.rows(question), row];
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

                const form = new FormData();
                form.append('questionId', question.id_questao_etapa);
                form.append('arquivo', file);

                const response = await fetch(Utils.createUrl('conectaente', 'resultAttachment', [this.entity.id]), { method: 'POST', body: form, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const body = await response.json().catch(() => null);
                event.target.value = '';

                this.afterAttachmentChange(question, response.ok, body);
            },

            async removeAttachment(question, entry) {
                const response = await this.api.POST(Utils.createUrl('conectaente', 'resultAttachmentDelete', [this.entity.id]), { questionId: question.id_questao_etapa, fileId: entry.fileId });
                const body = await response.json().catch(() => null);

                this.afterAttachmentChange(question, response.ok, body);
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

                return this.text(`estado_${state}`, { motivo: this.text(`motivo_${this.result?.reason}`) });
            },
        },
    });
})();
