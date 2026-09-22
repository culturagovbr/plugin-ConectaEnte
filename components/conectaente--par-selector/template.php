<?php

use MapasCulturais\i;

$this->import('mc-alert');
?>

<div v-if="!loading && hasData" class="conectaente--par-selector col-12">
    <label class="field__title"><?php i::_e('Dados do PAR') ?></label>

    <mc-alert v-if="!hasPar" type="danger" class="col-12">
        <?php i::_e('Os dados do PAR ainda não foram preenchidos. Selecione o exercício, a meta, a ação e a atividade abaixo.') ?>
    </mc-alert>

    <div class="conectaente--par-selector__fields grid-12">
        <div class="col-3 sm:col-12">
            <label class="field__title"><?php i::_e('Exercício') ?></label>
            <select v-model="exercicioId">
                <option value=""><?php i::_e('Selecione') ?></option>
                <option v-for="item in exercicios" :key="item.id" :value="item.id">{{ label(item) }}</option>
            </select>
        </div>

        <div class="col-3 sm:col-12">
            <label class="field__title"><?php i::_e('Meta') ?></label>
            <select v-model="metaId" :disabled="!exercicioId">
                <option value=""><?php i::_e('Selecione') ?></option>
                <option v-for="item in metas" :key="item.id" :value="item.id">{{ label(item) }}</option>
            </select>
        </div>

        <div class="col-3 sm:col-12">
            <label class="field__title"><?php i::_e('Ação') ?></label>
            <select v-model="acaoId" :disabled="!metaId">
                <option value=""><?php i::_e('Selecione') ?></option>
                <option v-for="item in acoes" :key="item.id" :value="item.id">{{ label(item) }}</option>
            </select>
        </div>

        <div class="col-3 sm:col-12">
            <label class="field__title"><?php i::_e('Atividade') ?></label>
            <select v-model="atividadeId" :disabled="!acaoId">
                <option value=""><?php i::_e('Selecione') ?></option>
                <option v-for="item in atividades" :key="item.id" :value="item.id">{{ label(item) }}</option>
            </select>
        </div>
    </div>
</div>
