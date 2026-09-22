app.component('conectaente--par-selector', {
    template: $TEMPLATES['conectaente--par-selector'],

    props: {
        entity: {
            type: Object,
            required: true,
        },
    },

    setup() {
        const text = Utils.getTexts('conectaente--par-selector');
        const api = new API('conectaente');

        return { text, api };
    },

    data() {
        return {
            loading: true,
            tree: null,
            exercicioId: this.entity.parExercicioId || '',
            metaId: this.entity.parMetaId || '',
            acaoId: this.entity.parAcaoId || '',
            atividadeId: this.entity.parAtividadeId || '',
        };
    },

    computed: {
        exercicios() {
            return this.tree?.exercicios || [];
        },

        hasData() {
            return this.exercicios.length > 0;
        },

        hasPar() {
            return !!(this.exercicioId || this.metaId || this.acaoId || this.atividadeId);
        },

        selectedExercicio() {
            return this.exercicios.find((item) => item.id === this.exercicioId) || null;
        },

        metas() {
            return this.selectedExercicio?.metas || [];
        },

        selectedMeta() {
            return this.metas.find((item) => item.id === this.metaId) || null;
        },

        acoes() {
            return this.selectedMeta?.acoes || [];
        },

        selectedAcao() {
            return this.acoes.find((item) => item.id === this.acaoId) || null;
        },

        atividades() {
            return this.selectedAcao?.atividades || [];
        },
    },

    watch: {
        exercicioId() {
            this.metaId = '';
        },

        metaId() {
            this.acaoId = '';
        },

        acaoId() {
            this.atividadeId = '';
        },

        atividadeId(value) {
            if (value) {
                this.persist();
            }
        },
    },

    async mounted() {
        const response = await this.api.GET(Utils.createUrl('conectaente', 'parInformation', [this.entity.id]));
        const body = response.ok ? await response.json().catch(() => null) : null;

        this.tree = body;
        this.loading = false;
    },

    methods: {
        label(item) {
            return item.nome || `${this.text('#')}${item.id}`;
        },

        persist() {
            this.entity.parExercicioId = this.exercicioId;
            this.entity.parMetaId = this.metaId;
            this.entity.parAcaoId = this.acaoId;
            this.entity.parAtividadeId = this.atividadeId;

            this.entity.save();
        },
    },
});
