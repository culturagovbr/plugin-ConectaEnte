// escopo próprio: o componente roda ao lado dos do tema, que declaram nomes no global
(() => {
    const DOCUMENT_TYPES_WITH_CNPJ = ['MEI', 'Pessoa Jurídica'];

    app.component('conectaente--result-mapping', {
        template: $TEMPLATES['conectaente--result-mapping'],

        emits: ['saved', 'saving'],

        props: {
            entity: {
                type: Entity,
                required: true,
            },
        },

        setup() {
            const api = new API('conectaente');
            const text = Utils.getTexts('conectaente--result-mapping');

            const messages = useMessages();
            const documentKinds = [{ key: 'cpf', label: 'CPF' }, { key: 'cnpj', label: 'CNPJ' }];

            return { api, text, messages, documentKinds };
        },

        data() {
            return {
                view: null,
                errors: {},
                loading: true,
                loadFailed: false,
                saving: false,
                queue: Promise.resolve(),
            };
        },

        computed: {
            fieldsById() {
                return Object.fromEntries((this.view?.fields || []).map((field) => [field.id, field]));
            },

            // por etapa da ficha: o mesmo título se repete de uma etapa para outra
            fieldGroups() {
                const fields = this.view?.fields || [];
                const hasManyPhases = new Set(fields.map((field) => field.phaseId)).size > 1;
                const groups = new Map();

                for (const field of fields) {
                    const step = field.step || this.text('sem_etapa');
                    const label = hasManyPhases && field.phase ? `${field.phase} · ${step}` : step;

                    groups.set(label, [...(groups.get(label) || []), field]);
                }

                return [...groups].map(([label, fields]) => ({ label, fields }));
            },

            hasSuggestion() {
                return Object.keys(this.view?.suggestion || {}).some((column) => !this.view.mapping.columns[column]);
            },
        },

        watch: {
            saving(isSaving) {
                this.$emit('saving', isSaving);
            },
        },

        mounted() {
            this.load();
        },

        beforeUnmount() {
            this.$emit('saving', false);
        },

        methods: {
            async load() {
                try {
                    const response = await this.api.GET(Utils.createUrl('conectaente', 'resultMapping', [this.entity.id]), {}, { cache: 'no-store' });

                    if (!response.ok) {
                        throw new Error(response.status);
                    }

                    this.view = await response.json();
                } catch (error) {
                    this.loadFailed = true;
                } finally {
                    this.loading = false;
                }
            },

            // uma gravação por vez, na ordem das escolhas
            save(changes) {
                this.queue = this.queue.then(async () => {
                    this.saving = true;

                    try {
                        const response = await this.api.POST(Utils.createUrl('conectaente', 'resultMapping', [this.entity.id]), changes);

                        if (!response.ok) {
                            throw new Error(response.status);
                        }

                        const body = await response.json();

                        this.errors = body.errors || {};
                        this.view = body.mapping;
                        this.$emit('saved');
                    } catch (error) {
                        this.messages.error(this.text('erro_salvar'));
                    } finally {
                        this.saving = false;
                    }
                });
            },

            fieldId(...parts) {
                return ['conectaente-result-mapping', ...parts].join('-').replace(/\s+/g, '-');
            },

            suggested(column) {
                const id = this.view?.suggestion?.[column.coluna];

                return id && !this.view.mapping.columns[column.coluna] ? this.fieldsById[id] : null;
            },

            chooseColumn(column, event) {
                const value = event.target.value === '' ? null : Number(event.target.value);
                this.save({ columns: { [column.coluna]: value } });
            },

            chooseDocument(type, kind, event) {
                const value = event.target.value === '' ? null : Number(event.target.value);
                this.save({ documents: { [type]: { [kind]: value } } });
            },

            chooseEquivalence(column, value, event) {
                this.save({ equivalences: { [column]: { [value]: event.target.value } } });
            },

            confirmSuggestion() {
                this.save({ confirmSuggestion: true });
            },

            hasCnpj(type) {
                return DOCUMENT_TYPES_WITH_CNPJ.includes(type);
            },

            columnByKey(key) {
                return (this.view?.columns || []).find((column) => column.coluna === key);
            },
        },
    });
})();
