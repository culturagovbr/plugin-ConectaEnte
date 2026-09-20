<?php

use MapasCulturais\i;

$this->import('
    conectaente--entity-form
    mc-avatar
    mc-confirm-button
    mc-icon
    mc-modal
    mc-tab
    mc-tabs
    mc-tag-list
    mc-title
    select-entity
');
?>

<mc-tabs class="entity-tabs entities-list" sync-hash>
    <mc-tab label="<?= i::esc_attr__('Entes Federados') ?>" slug="entities">
        <form class="entity-tabs__filters panel__row" @submit="$event.preventDefault();">
            <input type="search" class="entity-tabs__search-input"
                aria-label="<?= i::esc_attr__('Buscar Ente Federado') ?>"
                placeholder="<?= i::esc_attr__('Buscar por nome ou CNPJ') ?>"
                v-model="keyword">
        </form>

        <p v-if="!entities.length" class="panel__row entities-list__empty">
            <?php i::_e('Nenhum Ente Federado cadastrado.') ?>
        </p>
        <p v-else-if="!visibleEntities.length" class="panel__row entities-list__empty">
            <?php i::_e('Nenhum Ente Federado corresponde à busca.') ?>
        </p>

        <article v-for="entity in visibleEntities" :key="entity.id" class="panel__row panel-entity-card">
            <header class="panel-entity-card__header">
                <div class="left">
                    <div class="panel-entity-card__header--picture agent__background">
                        <mc-icon name="agent"></mc-icon>
                    </div>
                    <div class="panel-entity-card__header--info">
                        <mc-title tag="h2" :shortLength="100" :longLength="110">{{ entity.name }}</mc-title>
                        <p class="panel-entity-card__header--info-subtitle"><?php i::_e('CNPJ:') ?> {{ entity.document }}</p>
                    </div>
                </div>
                <div class="right">
                    <conectaente--entity-form :entity="entity"></conectaente--entity-form>
                </div>
            </header>

            <main class="panel-entity-card__main">
                <div class="grid-12">
                    <div class="col-6 sm:col-12 cardKey__public">
                        <div class="cardKey__public--header">
                            <div class="label"><?= i::__('Selo:') ?></div>
                        </div>
                        <div class="cardKey__public--content">
                            <div class="entity-seals__seals">
                                <div v-for="seal in entity.seals" :key="seal.id" class="entity-seals__seals--seal">
                                    <div class="seal-icon" v-tooltip="seal.name">
                                        <div v-if="seal.files?.avatar" class="image">
                                            <mc-avatar :entity="seal" size="small" square></mc-avatar>
                                        </div>
                                        <mc-icon v-else name="seal"></mc-icon>

                                        <div class="icon">
                                            <mc-confirm-button @confirm="removeSeal(entity)">
                                                <template #button="modal">
                                                    <mc-icon @click="modal.open()" name="delete"></mc-icon>
                                                </template>
                                                <template #message>
                                                    <?php i::_e('Remover o selo deste Ente Federado?') ?>
                                                </template>
                                            </mc-confirm-button>
                                        </div>
                                    </div>
                                    <span class="seal-label">{{ seal.name }}</span>
                                </div>

                                <select-entity v-if="!entity.seals.length" type="seal" openside="down-right" @select="addSeal(entity, $event)">
                                    <template #button="{ toggle }">
                                        <div class="entity-seals__seals--addSeal" @click="toggle()" v-tooltip="'<?= i::esc_attr__('Cadastrar selo') ?>'">
                                            <mc-icon name="add"></mc-icon>
                                        </div>
                                    </template>
                                </select-entity>

                                <mc-tag-list v-if="!entity.seals.length" :tags="['<?= i::esc_attr__('Sem selo: nenhuma oportunidade é reconhecida') ?>']" classes="danger__background"></mc-tag-list>
                                <mc-tag-list v-if="unusableSeals(entity).length" :tags="['<?= i::esc_attr__('Selo indisponível: as oportunidades deixam de ser reconhecidas') ?>']" classes="danger__background"></mc-tag-list>
                            </div>
                        </div>
                    </div>

                    <div class="col-6 sm:col-12 cardKey__private">
                        <div class="cardKey__private--header">
                            <div class="label"><?= i::__('Token do CultBR:') ?></div>
                            <a class="view" @click="toggleToken(entity)"><mc-icon name="eye-view"></mc-icon></a>
                            <a class="copy" @click="copyToken(entity)"><mc-icon name="copy"></mc-icon></a>
                        </div>
                        <div class="cardKey__private--content"><span>{{ shownToken(entity) }}</span></div>
                    </div>
                </div>
            </main>
        </article>

        <mc-modal ref="passwordModal" title="<?= i::esc_attr__('Confirme sua senha') ?>" classes="create-modal" @close="password = ''">
            <template #default>
                <div class="create-modal__fields">
                    <div class="field">
                        <label><?php i::_e('Sua senha') ?></label>
                        <input type="password" v-model="password" autocomplete="current-password" @keyup.enter="confirmPassword()">
                        <p class="field__note"><?php i::_e('O token só é revelado ao administrador que confirmar a própria senha.') ?></p>
                    </div>
                </div>
            </template>

            <template #actions="modal">
                <button class="button button--primary" @click="confirmPassword()"><?php i::_e('Confirmar') ?></button>
                <button class="button button--text button--text-del" @click="modal.close()"><?php i::_e('Cancelar') ?></button>
            </template>
        </mc-modal>
    </mc-tab>
</mc-tabs>
