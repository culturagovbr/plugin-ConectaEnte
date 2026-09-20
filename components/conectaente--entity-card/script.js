app.component('conectaente--entity-card', {
    template: $TEMPLATES['conectaente--entity-card'],

    props: {
        entity: {
            type: Object,
            required: true,
        },
        trashed: {
            type: Boolean,
            default: false,
        },
        seals: {
            type: Array,
            default: () => [],
        },
    },

    setup() {
        const messages = useMessages();
        const text = Utils.getTexts('conectaente--entity-card');
        const api = new API('conectaente');

        return { messages, text, api };
    },

    data() {
        return {
            revealedToken: null,
            password: '',
            pendingAction: null,
        };
    },

    computed: {
        shownToken() {
            return this.revealedToken ?? this.entity.token;
        },

        unusableSeals() {
            return this.entity.seals.filter((seal) => !seal.usable).map((seal) => seal.name);
        },

        sealWithValidity() {
            return this.entity.seals.find((seal) => seal.validity > 0) ?? null;
        },

        sealValidityWarning() {
            return this.text('Selo com validade de {meses} meses: edite o selo e remova a validade').replace('{meses}', this.sealWithValidity?.validity);
        },

        passwordPrompt() {
            const prompts = {
                delete: 'Excluir manda o Ente Federado para a lixeira: ele deixa de integrar, e CNPJ, selo e token ficam reservados até recuperar ou excluir de vez.',
                undelete: 'Recuperar devolve o Ente Federado à listagem e à integração.',
                destroy: 'Excluir permanentemente apaga o Ente Federado, o vínculo com o selo e o token. Não dá para desfazer.',
            };

            return this.text(prompts[this.pendingAction] ?? 'O token só é revelado ao administrador que confirmar a própria senha.');
        },
    },

    methods: {
        url(action) {
            return Utils.createUrl('conectaente', action, [this.entity.id]);
        },

        addSeal(seal) {
            return this.submit(this.api.POST(this.url('federativeEntitySeal'), { sealId: seal.id }), this.text('Não foi possível alterar o selo.'));
        },

        removeSeal() {
            return this.submit(this.api.DELETE(this.url('federativeEntitySeal')), this.text('Não foi possível alterar o selo.'));
        },

        async submit(request, fallback) {
            const response = await request;

            if (response.ok) {
                location.reload();
            } else {
                this.messages.error(await this.firstMessage(response, fallback));
            }
        },

        toggleToken() {
            if (this.revealedToken) {
                this.revealedToken = null;
                return;
            }

            this.run('reveal');
        },

        // primeiro sem senha: dentro da janela o servidor aceita; fora dela responde 401 e o modal entra
        async run(action) {
            const response = await this.signedRequest(action);

            if (response.status === 401) {
                this.askPassword(action);
                return;
            }

            await this.settle(action, response);
        },

        askPassword(action) {
            this.pendingAction = action;
            this.password = '';
            this.$refs.passwordModal.open();
        },

        async confirmPassword() {
            const modal = this.$refs.passwordModal;

            modal.loading(true);
            const response = await this.signedRequest(this.pendingAction, this.password);
            modal.loading(false);

            if (!response.ok) {
                this.messages.error(await this.firstMessage(response, this.text('Não foi possível confirmar a senha.')));
                return;
            }

            await this.settle(this.pendingAction, response);
            modal.close();
        },

        async settle(action, response) {
            if (!response.ok) {
                this.messages.error(await this.firstMessage(response, this.text('Não foi possível concluir a ação.')));
                return;
            }

            if (action !== 'reveal' && action !== 'copy') {
                location.reload();
                return;
            }

            const { token } = await response.json();

            if (action === 'copy') {
                await this.copy(token);
            } else {
                this.revealedToken = token;
            }
        },

        signedRequest(action, password = null) {
            const body = password === null ? {} : { password };

            switch (action) {
                case 'delete': return this.api.DELETE(this.url('federativeEntity'), body);
                case 'undelete': return this.api.POST(this.url('federativeEntityUndelete'), body);
                case 'destroy': return this.api.DELETE(this.url('federativeEntityDestroy'), body);
                default: return this.api.POST(this.url('federativeEntityToken'), body);
            }
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
