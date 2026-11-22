// escopo próprio: o componente roda ao lado dos do tema, que declaram nomes no global
(() => {
    const DOCUMENT_TYPES_WITH_CNPJ = ['MEI', 'Pessoa Jurídica'];

    app.component('conectaente--result-mapping', {
        template: $TEMPLATES['conectaente--result-mapping'],

        props: {
            entity: {
                type: Entity,
                required: true,
            },
        },

        setup() {
            const api = new API('conectaente');
            const text = Utils.getTexts('conectaente--result-mapping');

            return { api, text };
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

            hasSuggestion() {
                return Object.keys(this.view?.suggestion || {}).some((column) => !this.view.mapping.columns[column]);
            },
        },

        mounted() {
            this.load();
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
                        const body = await response.json();

                        this.errors = body.errors || {};
                        this.view = body.mapping;
                    } catch (error) {
                        this.errors = { geral: this.text('erro_salvar') };
                    } finally {
                        this.saving = false;
                    }
                });
            },

            // o seletor agrupa por fase e etapa: há edital com dois campos "CNPJ"
            groupsFor(fieldIds) {
                const groups = {};

                for (const id of fieldIds) {
                    const field = this.fieldsById[id];

                    if (field) {
                        const label = [field.phase, field.step].filter(Boolean).join(' · ');
                        (groups[label] = groups[label] || []).push(field);
                    }
                }

                return groups;
            },

            allFieldIds() {
                return (this.view?.fields || []).map((field) => field.id);
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
