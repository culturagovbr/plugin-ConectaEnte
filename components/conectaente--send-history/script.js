const SEND_HISTORY_PAGE_SIZE = 20;

const SEND_HISTORY_OUTCOME_ICONS = {
    pending: 'clock',
    success: 'circle-checked',
    error: 'exclamation',
    simulated: 'code',
    abandoned: 'exchange',
    rejected: 'close',
};

app.component('conectaente--send-history', {
    template: $TEMPLATES['conectaente--send-history'],

    emits: [],

    props: {
        entity: {
            type: Entity,
            required: true,
        },
    },

    setup() {
        const api = new API('conectaente');
        const text = Utils.getTexts('conectaente--send-history');
        const messages = useMessages();

        return { api, text, messages };
    },

    data() {
        return {
            sends: [],
            total: 0,
            page: 0,
            loading: true,
            loadingMore: false,
            failureMessage: '',
        };
    },

    created() {
        this.load();
    },

    computed: {
        hasMore() {
            return this.sends.length < this.total;
        },
    },

    methods: {
        async load() {
            this.loading = true;

            try {
                await this.fetchPage(1);
                this.failureMessage = '';
            } catch (error) {
                this.failureMessage = error.message;
            } finally {
                this.loading = false;
            }
        },

        // falha ao paginar preserva o que já está em tela: só o aviso
        async loadMore() {
            this.loadingMore = true;

            try {
                await this.fetchPage(this.page + 1);
            } catch (error) {
                this.messages.error(error.message);
            } finally {
                this.loadingMore = false;
            }
        },

        async fetchPage(page) {
            const url = new URL(Utils.createUrl('conectaente', 'sendHistory', [this.entity.id]), window.location.origin);
            url.searchParams.set('page', page);
            url.searchParams.set('pageSize', SEND_HISTORY_PAGE_SIZE);

            const response = await this.api.GET(url, {}, { cache: 'no-store' });
            const body = await response.json().catch(() => null);

            if (!response.ok) {
                throw new Error(this.errorMessage(body));
            }

            this.sends = page === 1 ? body.sends : this.sends.concat(body.sends);
            this.total = body.total;
            this.page = page;
        },

        // o envelope de erro do core traz `error: true`; exibi-lo cru é como nasce o aviso dizendo "true"
        errorMessage(body) {
            const detail = body?.data ?? body?.message;

            return typeof detail === 'string' && detail ? detail : this.text('erro_carregar');
        },

        attemptKey(send, attempt) {
            return `${send.uuid}:${attempt.attempt}:${attempt.sentAt}`;
        },

        outcomeLabel(outcome) {
            return this.text(`desfecho_${outcome}`);
        },

        outcomeIcon(outcome) {
            return SEND_HISTORY_OUTCOME_ICONS[outcome] ?? 'circle';
        },

        attemptLabel(attempt) {
            return this.text('tentativa', { attempt: attempt.attempt, max: attempt.maxAttempts });
        },

        failedAttempt(attempt) {
            return attempt.outcome !== 'success' && attempt.outcome !== 'simulated';
        },

        failureOf(attempt) {
            if (attempt.httpStatus) {
                return this.text('http', { status: attempt.httpStatus });
            }

            return attempt.errorMessage || this.text('sem_resposta');
        },

        /** A resposta inteira num bloco só: status, cabeçalhos e, separado, o corpo. */
        responseText(attempt) {
            const head = [
                attempt.httpStatus ? this.text('http', { status: attempt.httpStatus }) : '',
                this.headerLines(attempt.responseHeaders),
            ].filter(Boolean).join('\n');

            return [head, this.prettyJson(attempt.responseBody)].filter(Boolean).join('\n\n');
        },

        headerLines(headers) {
            const value = this.parsed(headers);

            if (!value || typeof value !== 'object') {
                return value ?? '';
            }

            return Object.entries(value).map(([name, content]) => `${name}: ${content}`).join('\n');
        },

        /** O conteúdo é gravado serializado; o que não for JSON válido é exibido como veio. */
        prettyJson(content) {
            const value = this.parsed(content);

            if (value === null) {
                return '';
            }

            return typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value);
        },

        parsed(content) {
            if (!content) {
                return null;
            }

            try {
                return JSON.parse(content);
            } catch (error) {
                return content;
            }
        },

        formatDate(value) {
            const [date, time] = (value ?? '').split(' ');

            return date ? `${date.split('-').reverse().join('/')}, ${time}` : '';
        },

        async copy(content) {
            try {
                // `navigator.clipboard` só existe em contexto seguro, e o ambiente servido por HTTP puro não é um
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(content);
                } else if (!this.copyUsingSelection(content)) {
                    throw new Error('cópia não suportada neste contexto');
                }

                this.messages.success(this.text('copiado'));
            } catch (error) {
                this.messages.error(this.text('copia_indisponivel'));
            }
        },

        copyUsingSelection(content) {
            const field = document.createElement('textarea');
            field.value = content;
            field.setAttribute('readonly', '');
            field.style.position = 'fixed';
            field.style.top = '-9999px';
            document.body.appendChild(field);
            field.select();
            field.setSelectionRange(0, content.length);

            try {
                return document.execCommand('copy');
            } catch (error) {
                return false;
            } finally {
                field.remove();
            }
        },
    },
});
