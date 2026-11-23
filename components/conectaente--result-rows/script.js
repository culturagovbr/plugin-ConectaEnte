// escopo próprio: o componente roda ao lado dos do tema, que declaram nomes no global
(() => {
    const PAGE_SIZE = 50;
    const REGISTRATION_SLUG = 'conectaente_inscricao';

    app.component('conectaente--result-rows', {
        template: $TEMPLATES['conectaente--result-rows'],

        props: {
            entity: {
                type: Entity,
                required: true,
            },
        },

        setup() {
            const api = new API('conectaente');
            const text = Utils.getTexts('conectaente--result-rows');

            return { api, text, pageSize: PAGE_SIZE, registrationSlug: REGISTRATION_SLUG };
        },

        data() {
            return {
                summary: null,
                errors: {},
                loading: true,
                loadFailed: false,
            };
        },

        computed: {
            columns() {
                return this.summary?.columns || [];
            },

            headers() {
                return [
                    { text: this.text('inscricao'), value: 'number', slug: REGISTRATION_SLUG },
                    ...this.columns.map((column) => ({ text: column.nome, value: column.coluna, slug: column.coluna })),
                ];
            },

            query() {
                return { opportunity: this.entity.id };
            },
        },

        mounted() {
            this.loadSummary();
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
                const response = await this.api.POST(Utils.createUrl('conectaente', 'resultRows', [this.entity.id]), {
                    registrationId: row.registrationId,
                    column: column.coluna,
                    value,
                });

                if (response.ok) {
                    delete this.errors[key];
                    refresh();
                    this.loadSummary();
                    return;
                }

                const body = await response.json().catch(() => null);
                this.errors[key] = body?.data?.value?.[0] || this.text('erro_salvar');
            },

            cellError(row, column) {
                return this.errors[`${row.registrationId}:${column.coluna}`];
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
