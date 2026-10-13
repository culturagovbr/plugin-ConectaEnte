const SEND_STATUS_ICONS = {
    success: 'circle-checked',
    simulated: 'code',
    rejected: 'close',
    error: 'exclamation',
    awaiting: 'clock',
    ineligible: 'info',
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
        const config = $MAPAS.config.conectaenteSendStatus;

        return { text, config };
    },

    computed: {
        state() {
            return this.entity.conectaente_sendStatus || this.config.pendingSendState;
        },

        icon() {
            return SEND_STATUS_ICONS[this.state] ?? 'circle';
        },

        label() {
            return this.text(`situacao_${this.state}`, { data: this.sentAt });
        },

        sentAt() {
            const [date, time] = (this.entity.conectaente_sendAt ?? '').split(' ');

            return date ? `${date.split('-').reverse().join('/')} às ${(time ?? '').slice(0, 5)}` : '';
        },
    },
});
