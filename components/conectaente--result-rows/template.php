<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    entity-table
    mc-card
    mc-loading
');
?>
<mc-card v-if="loading || columns.length" class="conectaente-result-rows">
    <template #title>
        <h3><?php i::_e('Grade de contemplados') ?></h3>
        <p v-if="summary"><?php i::_e('Uma linha por inscrição aprovada na fase final. O valor preenchido aqui vale sobre o da ficha e o calculado; esvaziar a célula devolve o valor da ficha.') ?></p>
    </template>
    <template #content>
        <mc-loading :condition="loading"><?php i::_e('Carregando a grade') ?></mc-loading>
        <p v-if="loadFailed"><?php i::_e('Não foi possível carregar a grade agora.') ?></p>

        <template v-if="summary && columns.length">
            <p class="conectaente-result-tab__status">
                {{ summary.total }} <?php i::_e('contemplados.') ?>
                <strong v-if="summary.pendingRows">{{ summary.pendingRows }} <?php i::_e('com pendência.') ?></strong>
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
                hide-advanced-filters>
                <template #[registrationSlug]="{ entity: row }">
                    <strong>{{ row.number }}</strong><br>{{ row.name }}
                    <small v-if="row.pending.includes('tipo_proponente')" class="conectaente-result-question__error"><?php i::_e('Inscrição sem tipo de proponente.') ?></small>
                </template>

                <template v-for="column in columns" :key="column.coluna" #[column.coluna]="{ entity: row, refresh }">
                    <div class="conectaente-result-rows__cell" :class="{ 'conectaente-result-rows__cell--pending': isPending(row, column) }">
                        <select v-if="column.tipo === 'lista'" :value="row.cells[column.coluna].value ?? ''" @change="saveCell(row, column, $event.target.value || null, refresh)">
                            <option value=""></option>
                            <option v-for="option in column.opcoes" :key="option" :value="option">{{ option }}</option>
                            <option v-if="row.cells[column.coluna].value && !column.opcoes.includes(row.cells[column.coluna].value)" :value="row.cells[column.coluna].value">{{ row.cells[column.coluna].value }}</option>
                        </select>
                        <input v-else type="text" :value="row.cells[column.coluna].value ?? ''" @change="saveCell(row, column, $event.target.value === '' ? null : $event.target.value, refresh)">
                        <small class="conectaente-result-mapping__hint">{{ sourceLabel(row.cells[column.coluna]) }}</small>
                        <small v-if="cellError(row, column)" class="conectaente-result-question__error">{{ cellError(row, column) }}</small>
                    </div>
                </template>
            </entity-table>
        </template>
    </template>
</mc-card>
