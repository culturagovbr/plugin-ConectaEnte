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

    data() {
        return {
            keyword: '',
        };
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
                return this.entities;
            }

            return this.entities.filter((entity) => {
                const name = `${entity.name || ''}`.toLocaleLowerCase();
                const document = `${entity.document || ''}`.replace(/\D/g, '');

                return name.includes(keyword) || (digits !== '' && document.includes(digits));
            });
        },
    },
});
