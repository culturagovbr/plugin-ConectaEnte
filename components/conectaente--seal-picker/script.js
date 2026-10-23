globalThis.useConectaEnteSealCatalog = Pinia.defineStore('conectaente.sealCatalog', {
    state: () => ({ seals: [] }),

    actions: {
        fill(seals) {
            this.seals = seals;
        },

        take(seal) {
            this.seals = this.seals.filter((item) => item.id !== seal.id);
        },

        give(seal) {
            this.seals = [...this.seals, seal].sort((a, b) => a.name.localeCompare(b.name));
        },
    },
});

app.component('conectaente--seal-picker', {
    template: $TEMPLATES['conectaente--seal-picker'],
    emits: ['select'],

    props: {
        seals: {
            type: Array,
            default: () => [],
        },
    },

    data() {
        return {
            keyword: '',
        };
    },

    computed: {
        matches() {
            const keyword = this.keyword.trim().toLocaleLowerCase();

            if (!keyword) {
                return this.seals;
            }

            return this.seals.filter((seal) => `${seal.name || ''}`.toLocaleLowerCase().includes(keyword));
        },
    },

    methods: {
        select(seal, close) {
            this.$emit('select', seal);
            close();
        },
    },
});
