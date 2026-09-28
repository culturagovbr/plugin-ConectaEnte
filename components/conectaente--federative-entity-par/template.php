<?php
/**
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */
?>
<div class="conectaente-federative-entity-par" :class="{ 'conectaente-federative-entity-par--readonly': readonly }">
    <template v-if="readonly">
        <div class="field field--disabled conectaente-federative-entity-par__field">
            <label class="field__title">{{ text('labelExercise') }}</label>
            <div class="field__input">
                <div class="field__input--readonly">{{ readonlyExerciseLabel || text('unavailable') }}</div>
            </div>
        </div>
        <div class="field field--disabled conectaente-federative-entity-par__field">
            <label class="field__title">{{ text('labelGoal') }}</label>
            <div class="field__input">
                <div class="field__input--readonly">{{ readonlyGoalLabel || text('unavailable') }}</div>
            </div>
        </div>
        <div class="field field--disabled conectaente-federative-entity-par__field">
            <label class="field__title">{{ text('labelAction') }}</label>
            <div class="field__input">
                <div class="field__input--readonly">{{ readonlyActionLabel || text('unavailable') }}</div>
            </div>
        </div>
        <div class="field field--disabled conectaente-federative-entity-par__field">
            <label class="field__title">{{ text('labelActivity') }}</label>
            <div class="field__input">
                <div class="field__input--readonly">{{ readonlyActivityLabel || text('unavailable') }}</div>
            </div>
        </div>
    </template>

    <template v-else>
        <p v-if="exercises.length === 0" class="conectaente-federative-entity-par__empty">{{ emptyHint || text('emptyList') }}</p>
        <template v-else>
            <div class="field conectaente-federative-entity-par__field" data-field="conectaente_parExercicioId" :class="{ error: (showFieldErrors && fieldErrors.exercise) || serverErrorMessage('exercise') }">
                <label class="field__title">{{ text('labelExercise') }} <span class="required">*{{ text('required') }}</span></label>
                <div class="field__input">
                    <select v-model="exerciseId" required>
                        <option value="">{{ text('select') }}</option>
                        <option v-for="exercise in exercises" :key="exercise.id" :value="exercise.id">{{ exercise.ano ?? exercise.id }}</option>
                    </select>
                </div>
                <small v-if="(showFieldErrors && fieldErrors.exercise) || serverErrorMessage('exercise')" class="field__error">{{ serverErrorMessage('exercise') || fieldErrorMessage('exercise') }}</small>
            </div>

            <div class="field conectaente-federative-entity-par__field" data-field="conectaente_parMetaId" :class="{ error: (showFieldErrors && fieldErrors.goal) || serverErrorMessage('goal') }">
                <label class="field__title">{{ text('labelGoal') }} <span class="required">*{{ text('required') }}</span></label>
                <p v-if="exerciseHasNoGoals" class="conectaente-federative-entity-par__no-options">{{ text('noGoalsForExercise') }}</p>
                <div v-else class="field__input">
                    <select v-model="goalId" required :disabled="!exerciseId">
                        <option value="">{{ text('select') }}</option>
                        <option v-for="goal in goals" :key="goal.id" :value="goal.id">{{ goal.nome ?? '#' + goal.id }}</option>
                    </select>
                </div>
                <small v-if="(showFieldErrors && fieldErrors.goal) || serverErrorMessage('goal')" class="field__error">{{ serverErrorMessage('goal') || fieldErrorMessage('goal') }}</small>
            </div>

            <div class="field conectaente-federative-entity-par__field" data-field="conectaente_parAcaoId" :class="{ error: (showFieldErrors && fieldErrors.action) || serverErrorMessage('action') }">
                <label class="field__title">{{ text('labelAction') }} <span class="required">*{{ text('required') }}</span></label>
                <p v-if="goalHasNoActions" class="conectaente-federative-entity-par__no-options">{{ text('noActionsForGoal') }}</p>
                <div v-else class="field__input">
                    <select v-model="actionId" required :disabled="!goalId">
                        <option value="">{{ text('select') }}</option>
                        <option v-for="action in actions" :key="action.id" :value="action.id">{{ action.nome ?? '#' + action.id }}</option>
                    </select>
                </div>
                <small v-if="(showFieldErrors && fieldErrors.action) || serverErrorMessage('action')" class="field__error">{{ serverErrorMessage('action') || fieldErrorMessage('action') }}</small>
            </div>

            <div class="field conectaente-federative-entity-par__field" data-field="conectaente_parAtividadeId" :class="{ error: (showFieldErrors && fieldErrors.activity) || serverErrorMessage('activity') }">
                <label class="field__title">{{ text('labelActivity') }} <span class="required">*{{ text('required') }}</span></label>
                <p v-if="actionHasNoActivities" class="conectaente-federative-entity-par__no-options">{{ text('noActivitiesForAction') }}</p>
                <div v-else class="field__input">
                    <select v-model="activityId" required :disabled="!actionId">
                        <option value="">{{ text('select') }}</option>
                        <option v-for="activity in activities" :key="activity.id" :value="activity.id">{{ activity.nome ?? '#' + activity.id }}</option>
                    </select>
                </div>
                <small v-if="(showFieldErrors && fieldErrors.activity) || serverErrorMessage('activity')" class="field__error">{{ serverErrorMessage('activity') || fieldErrorMessage('activity') }}</small>
            </div>
        </template>
    </template>
</div>
