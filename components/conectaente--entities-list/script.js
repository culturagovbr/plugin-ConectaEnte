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
    },

    data() {
        return {
            keyword: '',
        };
    },

    computed: {
        visibleEntities() {
            const keyword = this.keyword.trim().toLocaleLowerCase();

            if (!keyword) {
                return this.entities;
            }

            return this.entities.filter((entity) => {
                const name = `${entity.name || ''}`.toLocaleLowerCase();
                const document = `${entity.document || ''}`.toLocaleLowerCase();

                return name.includes(keyword) || document.includes(keyword);
            });
        },
    },
});
