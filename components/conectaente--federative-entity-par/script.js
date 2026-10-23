/**
 * Cascata Exercício → Meta → Ação → Atividade do PAR, sobre a árvore que o chamador entrega.
 */
const PAR_METADATA_KEY_BY_FIELD = {
    exercise: 'conectaente_parExercicioId',
    goal: 'conectaente_parMetaId',
    action: 'conectaente_parAcaoId',
    activity: 'conectaente_parAtividadeId',
};

app.component('conectaente--federative-entity-par', {
    template: $TEMPLATES['conectaente--federative-entity-par'],
    emits: ['update:modelValue'],

    setup() {
        const text = Utils.getTexts('conectaente--federative-entity-par');

        return { text };
    },

    props: {
        /** A árvore do PAR: exercícios, com metas, ações e atividades encaixadas. */
        exercises: {
            type: Array,
            default: () => [],
        },
        /** `{ conectaente_parExercicioId, conectaente_parMetaId, conectaente_parAcaoId, conectaente_parAtividadeId }`, as chaves dos metadados. */
        modelValue: {
            type: Object,
            default: null,
        },
        /** Substitui a mensagem padrão de árvore vazia. */
        emptyHint: {
            type: String,
            default: '',
        },
        readonly: {
            type: Boolean,
            default: false,
        },
        /** Erros do servidor por metadado, como os de `entity.__validationErrors`. */
        serverErrors: {
            type: Object,
            default: null,
        },
    },

    data() {
        return {
            showFieldErrors: false,
            fieldErrors: {
                exercise: false,
                goal: false,
                action: false,
                activity: false,
            },
        };
    },

    computed: {
        normalizedModel() {
            const boundModel = this.modelValue;

            return {
                conectaente_parExercicioId: boundModel?.conectaente_parExercicioId != null ? String(boundModel.conectaente_parExercicioId) : '',
                conectaente_parMetaId: boundModel?.conectaente_parMetaId != null ? String(boundModel.conectaente_parMetaId) : '',
                conectaente_parAcaoId: boundModel?.conectaente_parAcaoId != null ? String(boundModel.conectaente_parAcaoId) : '',
                conectaente_parAtividadeId: boundModel?.conectaente_parAtividadeId != null ? String(boundModel.conectaente_parAtividadeId) : '',
            };
        },

        exerciseHasNoGoals() {
            return !!this.normalizedModel.conectaente_parExercicioId && this.goals.length === 0;
        },

        goalHasNoActions() {
            return !!this.normalizedModel.conectaente_parMetaId && this.actions.length === 0;
        },

        actionHasNoActivities() {
            return !!this.normalizedModel.conectaente_parAcaoId && this.activities.length === 0;
        },

        exerciseId: {
            get() {
                return this.normalizedModel.conectaente_parExercicioId;
            },
            set(selectedValue) {
                this.$emit('update:modelValue', {
                    conectaente_parExercicioId: this.asId(selectedValue),
                    conectaente_parMetaId: '',
                    conectaente_parAcaoId: '',
                    conectaente_parAtividadeId: '',
                });
                this.clearErrors();
            },
        },

        goalId: {
            get() {
                return this.normalizedModel.conectaente_parMetaId;
            },
            set(selectedValue) {
                this.$emit('update:modelValue', {
                    ...this.normalizedModel,
                    conectaente_parMetaId: this.asId(selectedValue),
                    conectaente_parAcaoId: '',
                    conectaente_parAtividadeId: '',
                });
                this.clearErrors();
            },
        },

        actionId: {
            get() {
                return this.normalizedModel.conectaente_parAcaoId;
            },
            set(selectedValue) {
                this.$emit('update:modelValue', {
                    ...this.normalizedModel,
                    conectaente_parAcaoId: this.asId(selectedValue),
                    conectaente_parAtividadeId: '',
                });
                this.clearErrors();
            },
        },

        activityId: {
            get() {
                return this.normalizedModel.conectaente_parAtividadeId;
            },
            set(selectedValue) {
                this.$emit('update:modelValue', {
                    ...this.normalizedModel,
                    conectaente_parAtividadeId: this.asId(selectedValue),
                });
                this.clearErrors();
            },
        },

        goals() {
            if (!this.normalizedModel.conectaente_parExercicioId || !this.exercises.length) {
                return [];
            }

            const selectedExercise = this.nodeById(this.exercises, this.normalizedModel.conectaente_parExercicioId);

            return Array.isArray(selectedExercise?.metas) ? selectedExercise.metas : [];
        },

        actions() {
            if (!this.normalizedModel.conectaente_parMetaId || !this.goals.length) {
                return [];
            }

            const selectedGoal = this.nodeById(this.goals, this.normalizedModel.conectaente_parMetaId);

            return Array.isArray(selectedGoal?.acoes) ? selectedGoal.acoes : [];
        },

        activities() {
            if (!this.normalizedModel.conectaente_parAcaoId || !this.actions.length) {
                return [];
            }

            const selectedAction = this.nodeById(this.actions, this.normalizedModel.conectaente_parAcaoId);

            return Array.isArray(selectedAction?.atividades) ? selectedAction.atividades : [];
        },

        readonlyExerciseLabel() {
            const exercise = this.nodeById(this.exercises, this.normalizedModel.conectaente_parExercicioId);

            return this.readonlyLabel(this.normalizedModel.conectaente_parExercicioId, exercise?.ano);
        },

        readonlyGoalLabel() {
            const goal = this.nodeById(this.goals, this.normalizedModel.conectaente_parMetaId);

            return this.readonlyLabel(this.normalizedModel.conectaente_parMetaId, goal?.nome);
        },

        readonlyActionLabel() {
            const action = this.nodeById(this.actions, this.normalizedModel.conectaente_parAcaoId);

            return this.readonlyLabel(this.normalizedModel.conectaente_parAcaoId, action?.nome);
        },

        readonlyActivityLabel() {
            const activity = this.nodeById(this.activities, this.normalizedModel.conectaente_parAtividadeId);

            return this.readonlyLabel(this.normalizedModel.conectaente_parAtividadeId, activity?.nome);
        },
    },

    methods: {
        // os ids da árvore chegam como número ou texto, e a comparação é sempre entre strings
        nodeById(nodes, id) {
            return nodes.find((node) => String(node.id) === String(id));
        },

        asId(selectedValue) {
            return selectedValue != null && selectedValue !== '' ? String(selectedValue) : '';
        },

        /** O rótulo resolvido, ou o próprio id quando o nó não tem nome. */
        readonlyLabel(id, name) {
            if (!id) {
                return '';
            }

            return name != null ? String(name) : String(id);
        },

        clearErrors() {
            this.showFieldErrors = false;
            this.fieldErrors = {
                exercise: false,
                goal: false,
                action: false,
                activity: false,
            };
        },

        /** Mostra os erros e devolve se os quatro níveis formam uma cadeia completa. Use por `ref`. */
        validate() {
            if (this.readonly) {
                this.clearErrors();

                return true;
            }

            const selection = this.normalizedModel;
            const errors = {
                exercise: !selection.conectaente_parExercicioId,
                goal: !selection.conectaente_parMetaId || this.exerciseHasNoGoals,
                action: !selection.conectaente_parAcaoId || this.goalHasNoActions,
                activity: !selection.conectaente_parAtividadeId || this.actionHasNoActivities,
            };

            this.showFieldErrors = true;
            this.fieldErrors = errors;

            return !errors.exercise && !errors.goal && !errors.action && !errors.activity;
        },

        /** A primeira mensagem do servidor para o nível, ou vazio. */
        serverErrorMessage(field) {
            const messages = this.serverErrors?.[PAR_METADATA_KEY_BY_FIELD[field]];

            return Array.isArray(messages) && messages.length ? messages[0] : '';
        },

        fieldErrorMessage(field) {
            if (field === 'goal' && this.exerciseHasNoGoals) {
                return this.text('noGoalsForExercise');
            }

            if (field === 'action' && this.goalHasNoActions) {
                return this.text('noActionsForGoal');
            }

            if (field === 'activity' && this.actionHasNoActivities) {
                return this.text('noActivitiesForAction');
            }

            const messageKeyByField = {
                exercise: 'errorExercise',
                goal: 'errorGoal',
                action: 'errorAction',
                activity: 'errorActivity',
            };

            return this.text(messageKeyByField[field]) || '';
        },
    },
});
