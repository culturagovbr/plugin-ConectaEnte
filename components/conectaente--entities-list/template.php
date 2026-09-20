<?php

use MapasCulturais\i;

$this->import('
    mc-icon
    mc-tab
    mc-tabs
    mc-tag-list
    mc-title
');
?>

<mc-tabs class="entity-tabs entities-list" sync-hash>
    <mc-tab label="<?= i::esc_attr__('Entes federados') ?>" slug="entities">
        <form class="entity-tabs__filters panel__row" @submit="$event.preventDefault();">
            <input type="search" class="entity-tabs__search-input"
                aria-label="<?= i::esc_attr__('Buscar ente federado') ?>"
                placeholder="<?= i::esc_attr__('Buscar por nome ou CNPJ') ?>"
                v-model="keyword">
        </form>

        <p v-if="!entities.length" class="panel__row entities-list__empty">
            <?php i::_e('Nenhum ente federado cadastrado.') ?>
        </p>
        <p v-else-if="!visibleEntities.length" class="panel__row entities-list__empty">
            <?php i::_e('Nenhum ente federado corresponde à busca.') ?>
        </p>

        <article v-for="entity in visibleEntities" :key="entity.id" class="panel__row panel-entity-card">
            <header class="panel-entity-card__header">
                <div class="left">
                    <div class="panel-entity-card__header--picture agent__background">
                        <mc-icon name="agent"></mc-icon>
                    </div>
                    <div class="panel-entity-card__header--info">
                        <mc-title tag="h2" :shortLength="100" :longLength="110">{{ entity.name }}</mc-title>
                        <p class="panel-entity-card__header--info-subtitle">{{ entity.document }}</p>
                    </div>
                </div>
            </header>

            <main class="panel-entity-card__main entities-list__seals">
                <mc-tag-list v-if="usableSeals(entity).length" :tags="usableSeals(entity)"></mc-tag-list>

                <template v-if="unusableSeals(entity).length">
                    <mc-tag-list :tags="unusableSeals(entity)" classes="entities-list__seal--unusable"></mc-tag-list>
                    <p class="entities-list__warning">
                        <mc-icon name="alert"></mc-icon>
                        <?php i::_e('Selo indisponível: as oportunidades com ele deixam de ser reconhecidas.') ?>
                    </p>
                </template>

                <p v-if="!entity.seals.length" class="entities-list__warning">
                    <mc-icon name="alert"></mc-icon>
                    <?php i::_e('Sem selo associado: nenhuma oportunidade é reconhecida como deste ente.') ?>
                </p>
            </main>
        </article>
    </mc-tab>
</mc-tabs>
