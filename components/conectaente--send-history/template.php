<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

$this->import('
    mc-accordion
    mc-alert
    mc-icon
    mc-loading
');
?>
<div class="conectaente-send-history">
    <mc-loading :condition="loading"></mc-loading>

    <template v-if="!loading">
        <mc-alert v-if="failureMessage" type="danger" role="alert">{{ failureMessage }}</mc-alert>

        <mc-alert v-else-if="!sends.length" type="helper">{{ text('vazio') }}</mc-alert>

        <template v-else>
            <mc-accordion v-for="send in sends" :key="send.uuid" class="conectaente-send-history__send">
                <template #title>
                    <span class="conectaente-send-history__heading">
                        <mc-icon :name="outcomeIcon(send.outcome)" :class="'conectaente-send-history__outcome--' + send.outcome" :title="outcomeLabel(send.outcome)" :aria-label="outcomeLabel(send.outcome)"></mc-icon>
                        <code class="conectaente-send-history__uuid">{{ send.uuid }}</code>
                        <small>{{ formatDate(send.startedAt) }}</small>
                        <small v-if="send.triggeredByUserId">{{ text('por_usuario', { id: send.triggeredByUserId }) }}</small>
                    </span>
                </template>

                <template #content>
                    <dl class="conectaente-send-history__meta">
                        <dt>{{ text('conclusao') }}</dt>
                        <dd>{{ send.outcome === 'pending' ? text('em_andamento') : formatDate(send.finishedAt) }}</dd>
                        <template v-if="send.parEditalId">
                            <dt>{{ text('par_edital') }}</dt>
                            <dd>{{ send.parEditalId }}</dd>
                        </template>
                    </dl>

                    <p v-if="!send.attempts.length">{{ text('sem_tentativa') }}</p>

                    <mc-accordion v-for="attempt in send.attempts" :key="attemptKey(send, attempt)" class="conectaente-send-history__attempt">
                        <template #title>
                            <span class="conectaente-send-history__heading">
                                <mc-icon :name="outcomeIcon(attempt.outcome)" :class="'conectaente-send-history__outcome--' + attempt.outcome" :title="outcomeLabel(attempt.outcome)" :aria-label="outcomeLabel(attempt.outcome)"></mc-icon>
                                <strong>{{ attemptLabel(attempt) }}</strong>
                                <small v-if="attempt.httpStatus">{{ text('http', { status: attempt.httpStatus }) }}</small>
                                <small v-if="attempt.durationMs !== null">{{ text('duracao', { ms: attempt.durationMs }) }}</small>
                            </span>
                        </template>

                        <template #content>
                            <dl class="conectaente-send-history__meta">
                                <dt>{{ text('enviado_em') }}</dt>
                                <dd>{{ formatDate(attempt.sentAt) }}</dd>
                                <dt>{{ text('endpoint') }}</dt>
                                <dd><code>{{ attempt.httpMethod }} {{ attempt.endpoint }}</code></dd>
                            </dl>

                            <p v-if="failedAttempt(attempt)" class="conectaente-send-history__failure">
                                <mc-icon name="exclamation"></mc-icon>
                                <span>{{ failureOf(attempt) }}</span>
                            </p>

                            <mc-accordion v-if="attempt.requestPayload" class="conectaente-send-history__content">
                                <template #title>{{ text('payload') }}</template>
                                <template #content>
                                    <div class="conectaente-send-history__code">
                                        <button class="conectaente-send-history__copy" type="button" @click="copy(prettyJson(attempt.requestPayload))">
                                            <mc-icon name="copy"></mc-icon>
                                            <span>{{ text('copiar') }}</span>
                                        </button>
                                        <pre tabindex="0" :aria-label="text('payload')">{{ prettyJson(attempt.requestPayload) }}</pre>
                                    </div>
                                </template>
                            </mc-accordion>

                            <mc-accordion v-if="responseText(attempt)" class="conectaente-send-history__content">
                                <template #title>{{ text('resposta') }}</template>
                                <template #content>
                                    <div class="conectaente-send-history__code">
                                        <button class="conectaente-send-history__copy" type="button" @click="copy(responseText(attempt))">
                                            <mc-icon name="copy"></mc-icon>
                                            <span>{{ text('copiar') }}</span>
                                        </button>
                                        <pre tabindex="0" :aria-label="text('resposta')">{{ responseText(attempt) }}</pre>
                                    </div>
                                </template>
                            </mc-accordion>
                        </template>
                    </mc-accordion>
                </template>
            </mc-accordion>

            <button v-if="hasMore" class="button button--secondary conectaente-send-history__more" type="button" @click="loadMore()" :disabled="loadingMore" :aria-busy="loadingMore">
                {{ text('carregar_mais') }}
            </button>
        </template>
    </template>
</div>
