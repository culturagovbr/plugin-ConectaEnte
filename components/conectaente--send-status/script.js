const SEND_STATUS_ICONS = {
    success: 'circle-checked',
    simulated: 'code',
    rejected: 'close',
    error: 'exclamation',
    awaiting: 'clock',
    ineligible: 'info',
    suspended: 'exclamation',
};

// o envio sai por job, segundos depois de salvar
const SEND_STATUS_POLL = { interval: 5000, attempts: 6 };

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
        const api = new API('conectaente');

        return { api, text };
    },

    data() {
        return {
            current: {
                ...$MAPAS.config.conectaenteSendStatus,
                status: this.entity.conectaente_sendStatus ?? null,
                at: this.entity.conectaente_sendAt ?? null,
            },
            pollTimer: null,
            attemptsLeft: 0,
        };
    },

    watch: {
        // muda quando a entidade é repovoada pelo servidor: salvar, publicar
        'entity.__originalValues': {
            deep: true,
            handler() {
                this.follow();
            },
        },
    },

    mounted() {
        if (this.current.pendingSendState === 'awaiting') {
            this.follow();
        }
    },

    beforeUnmount() {
        clearTimeout(this.pollTimer);
    },

    computed: {
        // sem selo não há badge, nem com desfecho gravado: o que vale é a integração de agora
        state() {
            if (!this.current.sealed) {
                return null;
            }

            return this.current.suspended ? 'suspended' : this.current.status || this.current.pendingSendState;
        },

        icon() {
            return SEND_STATUS_ICONS[this.state] ?? 'circle';
        },

        label() {
            return this.text(`situacao_${this.state}`, { data: this.sentAt });
        },

        sentAt() {
            const [date, time] = (this.current.at ?? '').split(' ');

            return date ? `${date.split('-').reverse().join('/')} às ${(time ?? '').slice(0, 5)}` : '';
        },
    },

    methods: {
        // acompanha até o desfecho mudar; depois do limite, quem quiser recarrega
        follow() {
            clearTimeout(this.pollTimer);
            this.attemptsLeft = SEND_STATUS_POLL.attempts;
            this.refresh();
        },

        async refresh() {
            const before = this.current.at;
            const response = await this.api.GET(Utils.createUrl('conectaente', 'sendStatus', [this.entity.id]), {}, { cache: 'no-store' }).catch(() => null);

            if (response?.ok) {
                this.current = await response.json();
            }

            if (this.current.sealed && this.current.at === before && --this.attemptsLeft > 0) {
                this.pollTimer = setTimeout(() => this.refresh(), SEND_STATUS_POLL.interval);
            }
        },
    },
});
