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
        anchors: {
            type: [Object, Array],
            default: () => ({}),
        },
        fieldGroups: {
            type: [Object, Array],
            default: () => ({}),
        },
        groupLabels: {
            type: Object,
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

    data() {
        return {
            targets: [],
            activeAnchor: null,
            openGroup: null,
            groupChosen: false,
            ticking: false,
            scrolling: { page: null, card: null },
        };
    },

    mounted() {
        window.addEventListener('scroll', this.onScroll, { passive: true });
    },

    unmounted() {
        window.removeEventListener('scroll', this.onScroll);
        this.targets.forEach(({ element }) => element.classList.remove('conectaente-field--active'));
    },

    computed: {
        fields() {
            return Object.keys(this.missing || {}).map((key) => {
                const anchor = this.anchors[key] || key;

                return {
                    key,
                    anchor,
                    label: this.labels[key] || key,
                    messages: this.missing[key],
                    reachable: this.targets.some((target) => target.anchor === anchor),
                    active: anchor === this.activeAnchor,
                    group: this.fieldGroups[key] || 'core',
                };
            });
        },

        groups() {
            return Object.keys(this.groupLabels).map((name) => ({
                name,
                label: this.groupLabels[name],
                count: this.fields.filter((field) => field.group === name).length,
            }));
        },

        openFields() {
            return this.fields.filter((field) => field.group === this.openGroup);
        },

        // no primeiro nível, o grupo do campo que está sendo lido é quem fica em destaque
        activeGroup() {
            return this.fields.find((field) => field.active)?.group ?? null;
        },
    },

    watch: {
        missing: {
            immediate: true,
            handler() {
                this.$nextTick(() => this.findTargets());
            },
        },

        openFields() {
            this.$nextTick(() => this.revealActive());
        },

        // a lista abre no primeiro grupo que tem pendência; depois disso quem manda é o usuário
        groups: {
            immediate: true,
            handler(groups) {
                if (!this.groupChosen && !this.openGroup) {
                    this.openGroup = groups.find((group) => group.count)?.name ?? null;
                }
            },
        },
    },

    methods: {
        chooseGroup(name) {
            this.groupChosen = true;
            this.openGroup = name;
        },

        // o campo pendente pode estar escondido por uma condição da aba, então a busca é a cada resposta
        findTargets() {
            const found = [];

            for (const key of Object.keys(this.missing || {})) {
                const anchor = this.anchors[key] || key;
                const element = this.target(anchor);

                if (element && !found.some((target) => target.anchor === anchor)) {
                    found.push({ anchor, element });
                }
            }

            this.targets = found.sort((a, b) => {
                const boxA = a.element.getBoundingClientRect();
                const boxB = b.element.getBoundingClientRect();

                return boxA.top - boxB.top || boxA.left - boxB.left;
            });

            this.updateActive();
        },

        onScroll() {
            if (this.ticking) {
                return;
            }

            this.ticking = true;
            requestAnimationFrame(() => {
                this.ticking = false;
                this.updateActive();
            });
        },

        readingLine() {
            return window.innerHeight * 0.3;
        },

        // ativo é o último campo cujo topo já passou da linha de leitura e que ainda está na tela
        updateActive() {
            const readingLine = this.readingLine();
            let current = null;

            for (const target of this.targets) {
                const box = target.element.getBoundingClientRect();

                if (box.top <= readingLine && box.bottom > 0) {
                    current = { target, box };
                }
            }

            const active = current ? this.pickInRow(current, readingLine) : null;

            if (active === this.activeAnchor) {
                return;
            }

            this.activeAnchor = active;
            this.highlightField();
            this.$nextTick(() => this.revealActive());
        },

        /**
         * Os campos que dividem a linha com este e o quanto de rolagem cabe até o próximo.
         *
         * A faixa vai até onde o próximo campo começa: pela altura do campo caberia menos de
         * um giro de roda para cada um dos que dividem a linha.
         */
        rowOf(box) {
            const tops = this.targets.map(({ element }) => element.getBoundingClientRect().top);

            return {
                sameRow: this.targets.filter((_, index) => Math.abs(tops[index] - box.top) <= 8),
                span: (tops.find((top) => top > box.top + 8) ?? box.bottom) - box.top,
            };
        },

        // campos lado a lado dividem a faixa de rolagem da linha: cada um acende no seu trecho
        pickInRow({ target, box }, readingLine) {
            const { sameRow, span } = this.rowOf(box);

            if (sameRow.length < 2) {
                return target.anchor;
            }

            const progress = Math.min(Math.max((readingLine - box.top) / span, 0), 0.999);

            return sameRow[Math.floor(progress * sameRow.length)].anchor;
        },

        highlightField() {
            for (const { anchor, element } of this.targets) {
                element.classList.toggle('conectaente-field--active', anchor === this.activeAnchor);
            }
        },

        // o card rola por dentro quando a lista não cabe; sem isto o destaque ficaria escondido
        revealActive() {
            const item = this.$refs.root?.querySelector('.conectaente-opportunity-requirements__item--active');
            const scroller = this.$refs.root?.closest('.mc-card');

            if (!item || !scroller || scroller.scrollHeight <= scroller.clientHeight) {
                return;
            }

            const itemBox = item.getBoundingClientRect();
            const scrollerBox = scroller.getBoundingClientRect();

            if (itemBox.top >= scrollerBox.top && itemBox.bottom <= scrollerBox.bottom) {
                return;
            }

            this.animateScroll(scroller, scroller.scrollTop + itemBox.top - scrollerBox.top - scrollerBox.height / 3);
        },

        /**
         * Rola até a posição, animando à mão.
         *
         * `behavior: 'smooth'` é ignorado em elemento nesta página e, na janela, é cancelado
         * pelo foco que o clique dá ao botão dentro do card rolável.
         */
        animateScroll(scroller, to) {
            const isWindow = scroller === window;
            // a rolagem do card e a da página correm juntas: cada uma cancela só a sua
            const lane = isWindow ? 'page' : 'card';
            const moveTo = (position) => isWindow ? window.scrollTo(0, position) : (scroller.scrollTop = position);

            cancelAnimationFrame(this.scrolling[lane]);

            const from = isWindow ? window.scrollY : scroller.scrollTop;
            const distance = to - from;
            const duration = 280;
            let start = null;

            const step = (now) => {
                start ??= now;

                const progress = Math.min((now - start) / duration, 1);
                const eased = progress < 0.5 ? 2 * progress * progress : 1 - Math.pow(-2 * progress + 2, 2) / 2;
                moveTo(from + distance * eased);

                this.scrolling[lane] = progress < 1 ? requestAnimationFrame(step) : null;
            };

            this.scrolling[lane] = requestAnimationFrame(step);

            // sem foco na aba o navegador não entrega quadros: garante o destino de qualquer jeito
            setTimeout(() => {
                if (this.scrolling[lane]) {
                    cancelAnimationFrame(this.scrolling[lane]);
                    this.scrolling[lane] = null;
                    moveTo(to);
                }
            }, duration + 100);
        },

        goToField(field) {
            const target = this.target(field.anchor);

            if (!target) {
                return;
            }

            // o campo para no seu trecho da linha de leitura, senão o destaque ficaria no vizinho
            const box = target.getBoundingClientRect();
            const { sameRow, span } = this.rowOf(box);
            const index = sameRow.findIndex(({ anchor }) => anchor === field.anchor);
            const offset = sameRow.length > 1 ? (index + 0.5) / sameRow.length * span : 4;
            const top = window.scrollY + box.top - this.readingLine() + offset;

            this.animateScroll(window, Math.max(top, 0));
        },

        target(anchor) {
            return document.querySelector(`.conectaente-opportunity-tab [data-field="${anchor}"]`);
        },
    },
});
