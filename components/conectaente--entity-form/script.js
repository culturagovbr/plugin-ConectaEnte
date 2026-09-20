app.component('conectaente--entity-form', {
    template: $TEMPLATES['conectaente--entity-form'],

    props: {
        entity: {
            type: Object,
            default: null,
        },
        seals: {
            type: Array,
            default: () => [],
        },
    },

    setup() {
        const messages = useMessages();
        const text = Utils.getTexts('conectaente--entity-form');
        const api = new API('conectaente');

        return { messages, text, api };
    },

    data() {
        return {
            name: '',
            seal: null,
            token: '',
        };
    },

    computed: {
        title() {
            return this.entity ? this.entity.name : this.text('Cadastrar Ente Federado');
        },
    },

    methods: {
        reset() {
            this.name = '';
            this.seal = null;
            this.token = '';
        },

        selectSeal(seal) {
            this.seal = seal;
        },

        async save(modal) {
            modal.loading(true);

            const response = await (this.entity ? this.update() : this.create());

            if (response.ok) {
                location.reload();
            } else {
                modal.loading(false);
                this.messages.error(await this.firstMessage(response));
            }
        },

        create() {
            return this.api.POST(Utils.createUrl('conectaente', 'federativeEntities'), {
                name: this.name,
                sealId: this.seal?.id,
                token: this.token,
            });
        },

        update() {
            return this.api.PATCH(Utils.createUrl('conectaente', 'federativeEntity', [this.entity.id]), {
                token: this.token,
            });
        },

        /**
         * O controller responde {error: true, data: {campo: [mensagens]}}.
         */
        async firstMessage(response) {
            const body = await response.json().catch(() => null);
            const fields = Object.values(body?.data ?? {});

            return fields[0]?.[0] ?? this.text('Não foi possível salvar.');
        },
    },
});
