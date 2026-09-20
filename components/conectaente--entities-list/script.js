app.component('conectaente--entities-list', {
    template: $TEMPLATES['conectaente--entities-list'],

    props: {
        entities: {
            type: Array,
            default: () => [],
        },
    },

    setup() {
        const messages = useMessages();
        const text = Utils.getTexts('conectaente--entities-list');
        const api = new API('conectaente');

        return { messages, text, api };
    },

    data() {
        return {
            keyword: '',
            revealedTokens: {},
            password: '',
            pendingReveal: null,
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

    methods: {
        unusableSeals(entity) {
            return entity.seals.filter((seal) => !seal.usable).map((seal) => seal.name);
        },

        addSeal(entity, seal) {
            return this.submit(this.api.POST(this.sealUrl(entity), { sealId: seal.id }));
        },

        removeSeal(entity) {
            return this.submit(this.api.DELETE(this.sealUrl(entity)));
        },

        sealUrl(entity) {
            return Utils.createUrl('conectaente', 'federativeEntitySeal', [entity.id]);
        },

        shownToken(entity) {
            return this.revealedTokens[entity.id] ?? entity.token;
        },

        toggleToken(entity) {
            if (this.revealedTokens[entity.id]) {
                delete this.revealedTokens[entity.id];
                return;
            }

            this.askPassword(entity, 'reveal');
        },

        copyToken(entity) {
            this.askPassword(entity, 'copy');
        },

        askPassword(entity, action) {
            this.pendingReveal = { entity, action };
            this.password = '';
            this.$refs.passwordModal.open();
        },

        async confirmPassword() {
            const modal = this.$refs.passwordModal;
            const { entity, action } = this.pendingReveal;
            const url = Utils.createUrl('conectaente', 'federativeEntityToken', [entity.id]);

            modal.loading(true);
            const response = await this.api.POST(url, { password: this.password });
            modal.loading(false);

            if (!response.ok) {
                this.messages.error(await this.firstMessage(response, this.text('Não foi possível obter o token.')));
                return;
            }

            const { token } = await response.json();

            if (action === 'copy') {
                await this.copy(token);
            } else {
                this.revealedTokens[entity.id] = token;
            }

            modal.close();
        },

        async copy(token) {
            try {
                await this.writeToClipboard(token);
                this.messages.success(this.text('token copiado para a área de transferência'));
            } catch {
                this.messages.error(this.text('Não foi possível copiar o token.'));
            }
        },

        // navigator.clipboard só existe em HTTPS; em HTTP resta o caminho antigo
        writeToClipboard(text) {
            if (navigator.clipboard) {
                return navigator.clipboard.writeText(text);
            }

            // dentro do modal aberto, senão o focus-trap dele impede a seleção
            const field = document.createElement('textarea');
            field.value = text;
            (document.activeElement?.closest('.modal-content') ?? document.body).append(field);
            field.select();
            const copied = document.execCommand('copy');
            field.remove();

            if (!copied) {
                throw new Error('copy');
            }
        },

        /**
         * O controller responde {error: true, data: {campo: [mensagens]}}.
         */
        async firstMessage(response, fallback) {
            const body = await response.json().catch(() => null);
            const fields = Object.values(body?.data ?? {});

            return fields[0]?.[0] ?? fallback;
        },
    },
});
