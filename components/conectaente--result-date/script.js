app.component('conectaente--result-date', {
    template: $TEMPLATES['conectaente--result-date'],

    emits: ['update:modelValue'],

    props: {
        // AAAA-MM-DD, como o CultBR recebe
        modelValue: {
            type: String,
            default: null,
        },
        inputId: {
            type: String,
            default: null,
        },
    },

    data() {
        return {
            typed: this.toDisplay(this.modelValue),
            locale: $MAPAS.config.locale,
            dayNames: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab'],
        };
    },

    computed: {
        picked() {
            return this.toIso(this.typed) ? new Date(`${this.toIso(this.typed)}T12:00:00`) : null;
        },
    },

    watch: {
        modelValue(value) {
            if (value !== this.toIso(this.typed)) {
                this.typed = this.toDisplay(value);
            }
        },
    },

    methods: {
        type(event) {
            this.typed = event.target.value;

            if (this.typed === '' || this.toIso(this.typed)) {
                this.update(this.toIso(this.typed));
            }
        },

        // data pela metade vai como está, para a validação recusar em vez de sumir
        leave() {
            this.update(this.toIso(this.typed) ?? (this.typed || null));
        },

        pick(date) {
            this.typed = date ? date.toLocaleDateString('pt-BR') : '';
            this.update(this.toIso(this.typed));
        },

        update(value) {
            if (value !== (this.modelValue ?? null)) {
                this.$emit('update:modelValue', value);
            }
        },

        toDisplay(value) {
            const parts = String(value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);

            return parts ? `${parts[3]}/${parts[2]}/${parts[1]}` : (value ?? '');
        },

        toIso(value) {
            const parts = String(value ?? '').match(/^(\d{2})\/(\d{2})\/(\d{4})$/);

            return parts ? `${parts[3]}-${parts[2]}-${parts[1]}` : null;
        },
    },
});
