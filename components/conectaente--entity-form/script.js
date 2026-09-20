app.component('conectaente--entity-form', {
    template: $TEMPLATES['conectaente--entity-form'],

    props: {
        entity: {
            type: Object,
            default: null,
        },
    },

    setup() {
        const messages = useMessages();
        const text = Utils.getTexts('conectaente--entity-form');
        const api = new API('conectaente');
        const catalog = useConectaEnteSealCatalog();

        return { messages, text, api, catalog };
    },

    data() {
        return {
            name: '',
            seal: null,
            token: '',
            errors: {},
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
            this.errors = {};
        },

        clearError(field) {
            delete this.errors[field];
        },

        selectSeal(seal) {
            this.seal = seal;
            this.clearError('seal');
        },

        async save(modal) {
            modal.loading(true);

            const response = await (this.entity ? this.update() : this.create());

            if (response.ok) {
                location.reload();
            } else {
                modal.loading(false);
                this.showErrors(await response.json().catch(() => null));
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
         * O controller responde {error: true, data: {campo: [mensagens]}}: cada campo mostra o seu; o resto vai ao toast.
         */
        showErrors(body) {
            // o CNPJ vem do token, então erro de CNPJ aparece sob o token
            const fieldOf = { name: 'name', seal: 'seal', token: 'token', document: 'token' };
            const errors = {};
            const loose = [];

            for (const [key, messages] of Object.entries(body?.data ?? {})) {
                const field = fieldOf[key];

                if (field) {
                    errors[field] = [...(errors[field] ?? []), ...messages];
                } else {
                    loose.push(...messages);
                }
            }

            this.errors = errors;

            if (loose.length || !Object.keys(errors).length) {
                this.messages.error(loose[0] ?? this.text('Não foi possível salvar.'));
            }
        },
    },
});
