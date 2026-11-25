// escopo próprio: o componente roda ao lado dos do tema, que declaram nomes no global
(() => {
    const PAGE_SIZE = 50;
    const REGISTRATION_SLUG = 'conectaente_inscricao';
    const MASKS = { cpf: '###.###.###-##', cnpj: '##.###.###/####-##', cep: '#####-###' };

    app.component('conectaente--result-rows', {
        template: $TEMPLATES['conectaente--result-rows'],

        emits: ['saved', 'saving', 'refused'],

        props: {
            entity: {
                type: Entity,
                required: true,
            },
            // muda a cada gravação do de/para, que refaz as células vindas da ficha
            mappingVersion: {
                type: Number,
                default: 0,
            },
            // o que o CultBR recusou, por `inscrição:coluna`
            refusals: {
                type: Object,
                default: () => ({}),
            },
        },

        setup() {
            const api = new API('conectaente');
            const text = Utils.getTexts('conectaente--result-rows');

            const messages = useMessages();

            return { api, text, messages, pageSize: PAGE_SIZE, registrationSlug: REGISTRATION_SLUG };
        },

        data() {
            return {
                summary: null,
                errors: {},
                // o que foi digitado e recusado, por célula
                drafts: {},
                loading: true,
                loadFailed: false,
                cellsSaving: 0,
            };
        },

        computed: {
            columns() {
                return this.summary?.columns || [];
            },

            headers() {
                return [
                    { text: this.text('inscricao'), value: 'number', slug: REGISTRATION_SLUG, sticky: true, width: '180px' },
                    ...this.columns.map((column) => ({ text: column.nome, value: column.coluna, slug: column.coluna })),
                ];
            },

            query() {
                return { opportunity: this.entity.id };
            },
        },

        watch: {
            mappingVersion() {
                this.loadSummary();
            },

            errors: {
                deep: true,
                handler() {
                    this.$emit('refused', Object.keys(this.errors).length);
                },
            },

            cellsSaving(total) {
                this.$emit('saving', total > 0);
            },
        },

        mounted() {
            this.loadSummary();
        },

        // trocar de aba desmonta a grade
        beforeUnmount() {
            this.$emit('saving', false);
            this.$emit('refused', 0);
        },

        methods: {
            // colunas, total e pendências; as linhas vêm pelo entity-table
            async loadSummary() {
                try {
                    const url = new URL(Utils.createUrl('conectaente', 'resultRows', [this.entity.id]), window.location.origin);
                    url.searchParams.set('pageSize', 1);
                    const response = await this.api.GET(url, {}, { cache: 'no-store' });

                    if (!response.ok) {
                        throw new Error(response.status);
                    }

                    this.summary = await response.json();
                    this.loadFailed = false;
                } catch (error) {
                    this.loadFailed = true;
                } finally {
                    this.loading = false;
                }
            },

            rowOf(row) {
                row.__objectId = `conectaente-result-row:${row.registrationId}`;

                return row;
            },

            async saveCell(row, column, value, refresh) {
                const key = `${row.registrationId}:${column.coluna}`;

                this.cellsSaving++;

                try {
                    const response = await this.api.POST(Utils.createUrl('conectaente', 'resultRows', [this.entity.id]), {
                        registrationId: row.registrationId,
                        column: column.coluna,
                        value,
                    });

                    if (response.ok) {
                        this.forget(key);
                        refresh();
                        this.loadSummary();
                        this.$emit('saved');
                        return;
                    }

                    const body = await response.json().catch(() => null);
                    this.refuse(key, value, body?.data?.value?.[0]);
                } catch (error) {
                    this.refuse(key, value);
                } finally {
                    this.cellsSaving--;
                }
            },

            refuse(key, value, reason) {
                this.drafts[key] = value;
                this.errors[key] = reason || this.text('erro_salvar');
            },

            forget(key) {
                delete this.drafts[key];
                delete this.errors[key];
            },

            shownValue(row, column) {
                const key = `${row.registrationId}:${column.coluna}`;

                return (key in this.drafts ? this.drafts[key] : row.cells[column.coluna].value) ?? '';
            },

            currencyOf(row, column) {
                const value = row.cells[column.coluna].value;

                return value === null || value === '' ? null : Number(value);
            },

            // o campo de moeda lê texto sem dígito como zero
            refuseText(event) {
                if (!/\d/.test(event.target.value)) {
                    event.target.value = '';
                }
            },

            // o campo de moeda também avisa ao montar: só o valor diferente é gravado
            saveCurrency(row, column, value, refresh) {
                if (value === this.currencyOf(row, column)) {
                    this.forget(`${row.registrationId}:${column.coluna}`);
                    return;
                }

                this.saveCell(row, column, value === null ? null : String(value), refresh);
            },

            maskOf(column) {
                return MASKS[column.tipo] || null;
            },

            cellLabel(row, column) {
                return `${column.nome}, ${row.number}`;
            },

            cellError(row, column) {
                const key = `${row.registrationId}:${column.coluna}`;

                return this.errors[key] || this.refusals[key];
            },

            isPending(row, column) {
                return row.pending.includes(column.coluna);
            },

            sourceLabel(cell) {
                return this.text(`origem_${cell.source}`);
            },
        },
    });
})();
