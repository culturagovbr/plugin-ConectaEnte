<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    mc-card
    mc-loading
');
?>
<mc-card v-if="loading || (grid && grid.columns.length)" class="conectaente-result-rows">
    <template #title>
        <h3><?php i::_e('Grade de contemplados') ?></h3>
        <p v-if="grid"><?php i::_e('Uma linha por inscrição aprovada na fase final. O valor preenchido aqui vale sobre o da ficha e o calculado; esvaziar a célula devolve o valor da ficha.') ?></p>
    </template>
    <template #content>
        <mc-loading :condition="loading && !grid"><?php i::_e('Carregando a grade') ?></mc-loading>
        <p v-if="loadFailed"><?php i::_e('Não foi possível carregar a grade agora.') ?></p>

        <template v-if="grid">
            <p class="conectaente-result-tab__status">
                {{ grid.total }} <?php i::_e('contemplados.') ?>
                <strong v-if="grid.pendingRows">{{ grid.pendingRows }} <?php i::_e('com pendência.') ?></strong>
            </p>

            <div class="conectaente-result-rows__scroll">
                <table class="conectaente-result-table">
                    <thead>
                        <tr>
                            <th><?php i::_e('Inscrição') ?></th>
                            <th v-for="column in grid.columns" :key="column.coluna">{{ column.nome }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in grid.rows" :key="row.registrationId" :class="{ 'conectaente-result-rows__row--pending': row.pending.length }">
                            <td>
                                <strong>{{ row.number }}</strong><br>{{ row.name }}
                                <small v-if="row.pending.includes('tipo_proponente')" class="conectaente-result-question__error"><?php i::_e('Inscrição sem tipo de proponente.') ?></small>
                            </td>
                            <td v-for="column in grid.columns" :key="column.coluna" :class="{ 'conectaente-result-rows__cell--pending': isPending(row, column) }">
                                <select v-if="column.tipo === 'lista'" :value="row.cells[column.coluna].value ?? ''" @change="saveCell(row, column, $event.target.value || null)">
                                    <option value=""></option>
                                    <option v-for="option in column.opcoes" :key="option" :value="option">{{ option }}</option>
                                    <option v-if="row.cells[column.coluna].value && !column.opcoes.includes(row.cells[column.coluna].value)" :value="row.cells[column.coluna].value">{{ row.cells[column.coluna].value }}</option>
                                </select>
                                <input v-else type="text" :value="row.cells[column.coluna].value ?? ''" @change="saveCell(row, column, $event.target.value === '' ? null : $event.target.value)">
                                <small class="conectaente-result-mapping__hint">{{ sourceLabel(row.cells[column.coluna]) }}</small>
                                <small v-if="cellError(row, column)" class="conectaente-result-question__error">{{ cellError(row, column) }}</small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="pages > 1" class="conectaente-result-rows__pages">
                <button type="button" class="button button--text button--sm" :disabled="page <= 1" @click="goTo(page - 1)"><?php i::_e('Anterior') ?></button>
                <span>{{ page }} / {{ pages }}</span>
                <button type="button" class="button button--text button--sm" :disabled="page >= pages" @click="goTo(page + 1)"><?php i::_e('Próxima') ?></button>
            </div>
        </template>
    </template>
</mc-card>
