/**
 * Liga a cascata do PAR à oportunidade: lê a árvore da rota e grava os metadados sem salvar a entidade.
 */
const PAR_SELECTION_KEYS = ['parExercicioId', 'parMetaId', 'parAcaoId', 'parAtividadeId'];

app.component('conectaente--par-selection', {
    template: $TEMPLATES['conectaente--par-selection'],

    props: {
        entity: {
            type: Entity,
            required: true,
        },
        classes: {
            type: [String, Array, Object],
            default: null,
        },
    },

    setup() {
        const api = new API('conectaente');
        const text = Utils.getTexts('conectaente--par-selection');

        return { api, text };
    },

    data() {
        return {
            exercises: [],
            // até a rota responder não há por que avisar de indisponibilidade
            available: true,
            selection: this.entitySelection(),
        };
    },

    watch: {
        // a entidade repovoada pelo servidor manda: o que não foi salvo não fica na tela
        'entity.__originalValues': {
            deep: true,
            handler() {
                this.selection = this.entitySelection();
            },
        },
    },

    mounted() {
        this.loadTree();
    },

    methods: {
        entitySelection() {
            return Object.fromEntries(PAR_SELECTION_KEYS.map((key) => [key, this.entity[key] ?? '']));
        },

        // a cadeia parcial vive só na tela: no servidor, ou estão os quatro, ou nenhum
        updateSelection(selection) {
            this.selection = selection;

            const isComplete = PAR_SELECTION_KEYS.every((key) => selection[key]);

            for (const key of PAR_SELECTION_KEYS) {
                this.entity[key] = isComplete ? selection[key] : null;
            }
        },

        async loadTree() {
            const url = Utils.createUrl('conectaente', 'parInformation', [this.entity.id]);

            try {
                const response = await this.api.GET(url, {}, { cache: 'no-store' });
                const parInformation = await response.json();

                if (!response.ok) {
                    throw parInformation;
                }

                this.available = parInformation.available;
                this.exercises = parInformation.exercicios || [];
            } catch (error) {
                this.available = false;
            }
        },
    },
});
