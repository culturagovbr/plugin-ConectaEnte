app.component('conectaente--entities-list', {
    template: $TEMPLATES['conectaente--entities-list'],

    props: {
        entities: {
            type: Array,
            default: () => [],
        },
        trashed: {
            type: Array,
            default: () => [],
        },
        seals: {
            type: Array,
            default: () => [],
        },
    },

    setup() {
        const catalog = useConectaEnteSealCatalog();

        return { catalog };
    },

    data() {
        return {
            keyword: '',
            cards: this.entities.map((entity) => ({ ...entity, seals: [...entity.seals] })),
        };
    },

    created() {
        this.catalog.fill(this.seals);
    },

    computed: {
        documentDigits() {
            const keyword = this.keyword.trim();

            return /^[\d.\/\-\s]+$/.test(keyword) ? keyword.replace(/\D/g, '') : '';
        },

        visibleEntities() {
            const keyword = this.keyword.trim().toLocaleLowerCase();
            const digits = this.documentDigits;

            if (!keyword) {
                return this.cards;
            }

            return this.cards.filter((entity) => {
                const name = `${entity.name || ''}`.toLocaleLowerCase();
                const document = `${entity.document || ''}`.replace(/\D/g, '');

                return name.includes(keyword) || (digits !== '' && document.includes(digits));
            });
        },
    },

    methods: {
        linkSeal(entity, seal) {
            entity.seals = [seal];
            this.catalog.take(seal);
        },

        unlinkSeal(entity) {
            const [seal] = entity.seals;

            entity.seals = [];
            this.catalog.give({ id: seal.id, name: seal.name, files: seal.files });
        },
    },
});
