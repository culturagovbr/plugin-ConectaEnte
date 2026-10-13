const SEND_STATUS_ICONS = {
    success: 'circle-checked',
    simulated: 'code',
    rejected: 'close',
    error: 'exclamation',
};

app.component('conectaente--send-status', {
    template: $TEMPLATES['conectaente--send-status'],

    emits: [],

    props: {
        entity: {
            type: Entity,
            required: true,
        },
    },

    setup() {
        const text = Utils.getTexts('conectaente--send-status');

        return { text };
    },

    computed: {
        outcome() {
            return this.entity.conectaente_sendStatus || null;
        },

        icon() {
            return SEND_STATUS_ICONS[this.outcome] ?? 'clock';
        },

        label() {
            return this.text(`situacao_${this.outcome}`, { data: this.sentAt });
        },

        sentAt() {
            const [date, time] = (this.entity.conectaente_sendAt ?? '').split(' ');

            return date ? `${date.split('-').reverse().join('/')} às ${(time ?? '').slice(0, 5)}` : '';
        },
    },
});
