// escopo próprio: o componente roda ao lado dos do tema, que declaram nomes no global
(() => {
    const PAGE_SIZE = 50;

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

            return { api, text };
        },

        data() {
            return {
                grid: null,
                page: 1,
                errors: {},
                loading: true,
                loadFailed: false,
            };
        },

        computed: {
            pages() {
                return this.grid ? Math.max(1, Math.ceil(this.grid.total / this.grid.pageSize)) : 1;
            },
        },

        mounted() {
            this.load();
        },

        methods: {
            async load() {
                this.loading = true;

                try {
                    const url = new URL(Utils.createUrl('conectaente', 'resultRows', [this.entity.id]), window.location.origin);
                    url.searchParams.set('page', this.page);
                    url.searchParams.set('pageSize', PAGE_SIZE);
                    const response = await this.api.GET(url, {}, { cache: 'no-store' });

                    if (!response.ok) {
                        throw new Error(response.status);
                    }

                    this.grid = await response.json();
                    this.loadFailed = false;
                } catch (error) {
                    this.loadFailed = true;
                } finally {
                    this.loading = false;
                }
            },

            async saveCell(row, column, value) {
                const key = `${row.registrationId}:${column.coluna}`;
                const response = await this.api.POST(Utils.createUrl('conectaente', 'resultRows', [this.entity.id]), {
                    registrationId: row.registrationId,
                    column: column.coluna,
                    value,
                });

                if (response.ok) {
                    delete this.errors[key];
                    await this.load();
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

            goTo(page) {
                this.page = Math.min(Math.max(1, page), this.pages);
                this.load();
            },

            sourceLabel(cell) {
                return this.text(`origem_${cell.source}`);
            },
        },
    });
})();
