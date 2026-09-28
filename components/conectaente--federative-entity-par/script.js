/**
 * Cascata Exercício → Meta → Ação → Atividade do PAR, sobre a árvore que o chamador entrega.
 */
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
        /** `{ parExercicioId, parMetaId, parAcaoId, parAtividadeId }`, as chaves dos metadados. */
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
                parExercicioId: boundModel?.parExercicioId != null ? String(boundModel.parExercicioId) : '',
                parMetaId: boundModel?.parMetaId != null ? String(boundModel.parMetaId) : '',
                parAcaoId: boundModel?.parAcaoId != null ? String(boundModel.parAcaoId) : '',
                parAtividadeId: boundModel?.parAtividadeId != null ? String(boundModel.parAtividadeId) : '',
            };
        },

        exerciseHasNoGoals() {
            return !!this.normalizedModel.parExercicioId && this.goals.length === 0;
        },

        goalHasNoActions() {
            return !!this.normalizedModel.parMetaId && this.actions.length === 0;
        },

        actionHasNoActivities() {
            return !!this.normalizedModel.parAcaoId && this.activities.length === 0;
        },

        exerciseId: {
            get() {
                return this.normalizedModel.parExercicioId;
            },
            set(selectedValue) {
                this.$emit('update:modelValue', {
                    parExercicioId: this.asId(selectedValue),
                    parMetaId: '',
                    parAcaoId: '',
                    parAtividadeId: '',
                });
                this.clearErrors();
            },
        },

        goalId: {
            get() {
                return this.normalizedModel.parMetaId;
            },
            set(selectedValue) {
                this.$emit('update:modelValue', {
                    ...this.normalizedModel,
                    parMetaId: this.asId(selectedValue),
                    parAcaoId: '',
                    parAtividadeId: '',
                });
                this.clearErrors();
            },
        },

        actionId: {
            get() {
                return this.normalizedModel.parAcaoId;
            },
            set(selectedValue) {
                this.$emit('update:modelValue', {
                    ...this.normalizedModel,
                    parAcaoId: this.asId(selectedValue),
                    parAtividadeId: '',
                });
                this.clearErrors();
            },
        },

        activityId: {
            get() {
                return this.normalizedModel.parAtividadeId;
            },
            set(selectedValue) {
                this.$emit('update:modelValue', {
                    ...this.normalizedModel,
                    parAtividadeId: this.asId(selectedValue),
                });
                this.clearErrors();
            },
        },

        goals() {
            if (!this.normalizedModel.parExercicioId || !this.exercises.length) {
                return [];
            }

            const selectedExercise = this.nodeById(this.exercises, this.normalizedModel.parExercicioId);

            return Array.isArray(selectedExercise?.metas) ? selectedExercise.metas : [];
        },

        actions() {
            if (!this.normalizedModel.parMetaId || !this.goals.length) {
                return [];
            }

            const selectedGoal = this.nodeById(this.goals, this.normalizedModel.parMetaId);

            return Array.isArray(selectedGoal?.acoes) ? selectedGoal.acoes : [];
        },

        activities() {
            if (!this.normalizedModel.parAcaoId || !this.actions.length) {
                return [];
            }

            const selectedAction = this.nodeById(this.actions, this.normalizedModel.parAcaoId);

            return Array.isArray(selectedAction?.atividades) ? selectedAction.atividades : [];
        },

        readonlyExerciseLabel() {
            const exercise = this.nodeById(this.exercises, this.normalizedModel.parExercicioId);

            return this.readonlyLabel(this.normalizedModel.parExercicioId, exercise?.ano);
        },

        readonlyGoalLabel() {
            const goal = this.nodeById(this.goals, this.normalizedModel.parMetaId);

            return this.readonlyLabel(this.normalizedModel.parMetaId, goal?.nome);
        },

        readonlyActionLabel() {
            const action = this.nodeById(this.actions, this.normalizedModel.parAcaoId);

            return this.readonlyLabel(this.normalizedModel.parAcaoId, action?.nome);
        },

        readonlyActivityLabel() {
            const activity = this.nodeById(this.activities, this.normalizedModel.parAtividadeId);

            return this.readonlyLabel(this.normalizedModel.parAtividadeId, activity?.nome);
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
                exercise: !selection.parExercicioId,
                goal: !selection.parMetaId || this.exerciseHasNoGoals,
                action: !selection.parAcaoId || this.goalHasNoActions,
                activity: !selection.parAtividadeId || this.actionHasNoActivities,
            };

            this.showFieldErrors = true;
            this.fieldErrors = errors;

            return !errors.exercise && !errors.goal && !errors.action && !errors.activity;
        },

        /** A primeira mensagem do servidor para o nível, ou vazio. */
        serverErrorMessage(field) {
            const metadataKeyByField = {
                exercise: 'parExercicioId',
                goal: 'parMetaId',
                action: 'parAcaoId',
                activity: 'parAtividadeId',
            };
            const messages = this.serverErrors?.[metadataKeyByField[field]];

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
