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
            ticking: false,
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
                };
            });
        },
    },

    watch: {
        missing: {
            immediate: true,
            handler() {
                this.$nextTick(() => this.findTargets());
            },
        },
    },

    methods: {
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

        // ativo é o último campo cujo topo já passou da linha de leitura e que ainda está na tela
        updateActive() {
            const readingLine = window.innerHeight * 0.3;
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

        // campos lado a lado dividem a faixa de rolagem da linha: cada um acende no seu trecho
        pickInRow({ target, box }, readingLine) {
            const rowTops = this.targets.map(({ element }) => element.getBoundingClientRect().top);
            const sameRow = this.targets.filter((_, index) => Math.abs(rowTops[index] - box.top) <= 8);

            if (sameRow.length < 2) {
                return target.anchor;
            }

            // a faixa vai até onde o próximo campo começa, senão caberia menos de um giro de roda por campo
            const nextTop = rowTops.find((top) => top > box.top + 8) ?? box.bottom;
            const progress = Math.min(Math.max((readingLine - box.top) / (nextTop - box.top), 0), 0.999);

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

        // animação à mão: behavior 'smooth' é ignorado em elemento nesta página, só funciona em window
        animateScroll(scroller, to) {
            const from = scroller.scrollTop;
            const distance = to - from;
            const duration = 280;
            let start = null;

            const step = (now) => {
                start ??= now;

                const progress = Math.min((now - start) / duration, 1);
                const eased = progress < 0.5 ? 2 * progress * progress : 1 - Math.pow(-2 * progress + 2, 2) / 2;

                scroller.scrollTop = from + distance * eased;

                if (progress < 1) {
                    requestAnimationFrame(step);
                }
            };

            requestAnimationFrame(step);
        },

        goToField(field) {
            const target = this.target(field.anchor);

            if (!target) {
                return;
            }

            // scrollIntoView suave é interrompido nesta página; scrollTo vai até o fim
            const box = target.getBoundingClientRect();
            const top = window.scrollY + box.top + box.height / 2 - window.innerHeight / 2;

            window.scrollTo({ top: Math.max(top, 0), behavior: 'smooth' });
        },

        target(anchor) {
            return document.querySelector(`.conectaente-opportunity-tab [data-field="${anchor}"]`);
        },
    },
});
