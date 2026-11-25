<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    entity-table
    mc-alert
    mc-card
    mc-currency-input
    mc-icon
    mc-loading
');
?>
<mc-card v-if="loading || loadFailed || columns.length" class="conectaente-result-rows">
    <template #title>
        <h3><?php i::_e('Grade de contemplados') ?></h3>
        <p v-if="summary"><?php i::_e('Uma linha por inscrição aprovada na fase final. O valor preenchido aqui vale sobre o da ficha e o calculado; esvaziar a célula devolve o valor da ficha.') ?></p>
    </template>
    <template #content>
        <mc-loading :condition="loading"><?php i::_e('Carregando a grade') ?></mc-loading>
        <mc-alert v-if="loadFailed" type="danger" small><?php i::_e('Não foi possível carregar a grade agora.') ?></mc-alert>

        <template v-if="summary && columns.length">
            <p class="conectaente-result-badge" :class="summary.pendingRows ? 'conectaente-result-badge--pending' : 'conectaente-result-badge--success'" role="status">
                <mc-icon :name="summary.pendingRows ? 'exclamation' : 'circle-checked'"></mc-icon>
                {{ text('contemplados', { total: summary.total }) }}
                <strong v-if="summary.pendingRows">{{ text('com_pendencia', { total: summary.pendingRows }) }}</strong>
            </p>

            <entity-table
                type="registration"
                controller="conectaente"
                endpoint="resultRows"
                identifier="conectaenteResultRows"
                order="id ASC"
                :query="query"
                :limit="pageSize"
                :headers="headers"
                :required="registrationSlug"
                all-headers
                :raw-processor="rowOf"
                hide-filters
                hide-sort
                hide-advanced-filters
                hide-actions
                hide-header>
                <template #[registrationSlug]="{ entity: row }">
                    <strong>{{ row.number }}</strong><br>{{ row.name }}
                    <small v-if="row.pending.includes('tipo_proponente')" class="field__error" role="alert"><?php i::_e('Inscrição sem tipo de proponente.') ?></small>
                </template>

                <template v-for="column in columns" :key="column.coluna" #[column.coluna]="{ entity: row, refresh }">
                    <div class="field conectaente-result-rows__cell" :class="{ 'conectaente-result-rows__cell--pending': isPending(row, column), error: cellError(row, column) }">
                        <div class="field__input">
                            <select v-if="column.tipo === 'lista'" :value="row.cells[column.coluna].value ?? ''" :aria-label="cellLabel(row, column)" @change="saveCell(row, column, $event.target.value || null, refresh)">
                                <option value=""><?php i::_e('Selecione') ?></option>
                                <option v-for="option in column.opcoes" :key="option" :value="option">{{ option }}</option>
                                <option v-if="row.cells[column.coluna].value && !column.opcoes.includes(row.cells[column.coluna].value)" :value="row.cells[column.coluna].value">{{ row.cells[column.coluna].value }}</option>
                            </select>
                            <mc-currency-input v-else-if="column.tipo === 'monetario'" :key="'moeda-' + row.cells[column.coluna].value" :model-value="currencyOf(row, column)" :model-modifiers="{ lazy: true }" :aria-label="cellLabel(row, column)" @update:model-value="saveCurrency(row, column, $event, refresh)"></mc-currency-input>
                            <input v-else-if="maskOf(column)" v-maska :data-maska="maskOf(column)" type="text" :value="row.cells[column.coluna].value ?? ''" :aria-label="cellLabel(row, column)" autocomplete="off" @change="saveCell(row, column, $event.target.value === '' ? null : $event.target.value, refresh)">
                            <input v-else type="text" :value="row.cells[column.coluna].value ?? ''" :aria-label="cellLabel(row, column)" autocomplete="off" @change="saveCell(row, column, $event.target.value === '' ? null : $event.target.value, refresh)">
                        </div>
                        <small class="field__description">{{ sourceLabel(row.cells[column.coluna]) }}</small>
                        <small v-if="cellError(row, column)" class="field__error" role="alert">{{ cellError(row, column) }}</small>
                    </div>
                </template>
            </entity-table>
        </template>
    </template>
</mc-card>
