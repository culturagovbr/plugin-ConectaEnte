app.component('conectaente--targeting-multiselect', {
    template: $TEMPLATES['conectaente--targeting-multiselect'],

    props: {
        entity: {
            type: Entity,
            required: true,
        },
        prop: {
            type: String,
            required: true,
        },
        otherProp: {
            type: String,
            default: null,
        },
        otherOption: {
            type: String,
            default: null,
        },
        allOptions: {
            type: Boolean,
            default: false,
        },
        required: {
            type: Boolean,
            default: false,
        },
        classes: {
            type: [String, Array, Object],
            default: null,
        },
    },

    setup() {
        const keys = $MAPAS.config.conectaenteTargetingMultiselect;

        return { notTargetedKey: keys.notTargeted, allOptionsKey: keys.allOptions };
    },

    computed: {
        description() {
            return this.entity.$PROPERTIES?.[this.prop] || null;
        },

        values() {
            return this.entity[this.prop] || [];
        },

        selectableOptions() {
            const options = {};

            for (const [key, label] of Object.entries(this.description?.options || {})) {
                if (!this.isSynthetic(key)) {
                    options[key] = label;
                }
            }

            return options;
        },

        // "todas as opções" cobre todas as chaves, menos "Outros"
        optionsCoveredByAll() {
            return Object.keys(this.selectableOptions).filter((key) => key !== this.otherOption);
        },

        selectedTags() {
            return this.values.filter((key) => !this.isSynthetic(key));
        },

        isNotTargeted() {
            return this.values.length === 1 && this.values[0] === this.notTargetedKey;
        },

        isAllOptionsSelected() {
            return this.values.includes(this.allOptionsKey);
        },

        isOtherSelected() {
            return !!this.otherOption && this.values.includes(this.otherOption);
        },

        errors() {
            return this.entity.__validationErrors?.[this.prop] || [];
        },

        titleId() {
            return `${this.prop}-title`;
        },
    },

    methods: {
        isSynthetic(key) {
            return key === this.notTargetedKey || key === this.allOptionsKey;
        },

        // sem lista no metadado, o mc-multiselect pôs a chave num array avulso
        onSelect(key) {
            if (!Array.isArray(this.entity[this.prop])) {
                this.entity[this.prop] = [key];
            }
        },

        onNotTargetedChange(event) {
            if (event.target.checked) {
                this.entity[this.prop] = [this.notTargetedKey];
                this.clearOther();
            } else {
                this.entity[this.prop] = [];
            }
        },

        onAllOptionsChange(event) {
            this.entity[this.prop] = event.target.checked ? [this.allOptionsKey, ...this.optionsCoveredByAll] : [];
            this.clearOther();
        },

        // chamado pela tag e pelo dropdown; o dropdown já tirou a chave da lista
        onRemove(tag) {
            this.removeValue(tag);

            if (!this.optionsCoveredByAll.every((key) => this.values.includes(key))) {
                this.removeValue(this.allOptionsKey);
            }

            if (tag === this.otherOption) {
                this.clearOther();
            }
        },

        removeValue(key) {
            const index = this.values.indexOf(key);

            if (index >= 0) {
                this.entity[this.prop].splice(index, 1);
            }
        },

        clearOther() {
            if (this.otherProp) {
                this.entity[this.otherProp] = null;
            }
        },
    },
});
