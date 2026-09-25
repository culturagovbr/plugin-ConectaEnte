app.component('conectaente--opportunity-requirements', {
    template: $TEMPLATES['conectaente--opportunity-requirements'],

    props: {
        // null enquanto a rota não respondeu; [] quando não há pendência, e objeto quando há
        missing: {
            type: [Object, Array],
            default: null,
        },
        labels: {
            type: [Object, Array],
            required: true,
        },
        loading: {
            type: Boolean,
            default: false,
        },
        failed: {
            type: Boolean,
            default: false,
        },
    },

    computed: {
        fields() {
            return Object.keys(this.missing || {}).map((key) => ({
                key,
                label: this.labels[key] || key,
                messages: this.missing[key],
            }));
        },
    },
});
