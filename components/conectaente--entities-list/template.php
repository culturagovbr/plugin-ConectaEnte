<?php

use MapasCulturais\i;

$this->import('
    conectaente--entity-card
    mc-icon
    mc-tab
    mc-tabs
');
?>

<mc-tabs class="entity-tabs entities-list" sync-hash>
    <template #header="{ tab }">
        <mc-icon v-if="tab.slug === 'trash'" name="trash"></mc-icon>
        {{ tab.label }}
    </template>

    <mc-tab label="<?= i::esc_attr__('Entes Federados') ?>" slug="entities">
        <form class="entity-tabs__filters panel__row" @submit="$event.preventDefault();">
            <input type="search" class="entity-tabs__search-input"
                aria-label="<?= i::esc_attr__('Buscar Ente Federado') ?>"
                placeholder="<?= i::esc_attr__('Buscar por nome ou CNPJ') ?>"
                v-model="keyword">
        </form>

        <p v-if="!cards.length" class="panel__row entities-list__empty">
            <?php i::_e('Nenhum Ente Federado cadastrado.') ?>
        </p>
        <p v-else-if="!visibleEntities.length" class="panel__row entities-list__empty">
            <?php i::_e('Nenhum Ente Federado corresponde à busca.') ?>
        </p>

        <conectaente--entity-card v-for="entity in visibleEntities" :key="entity.id" :entity="entity" :seals="catalog.seals"
            @seal-linked="linkSeal(entity, $event)" @seal-unlinked="unlinkSeal(entity)"></conectaente--entity-card>
    </mc-tab>

    <mc-tab label="<?= i::esc_attr__('Lixeira') ?>" slug="trash">
        <p v-if="!trashed.length" class="panel__row entities-list__empty">
            <?php i::_e('A lixeira está vazia.') ?>
        </p>

        <conectaente--entity-card v-for="entity in trashed" :key="entity.id" :entity="entity" trashed></conectaente--entity-card>
    </mc-tab>
</mc-tabs>
