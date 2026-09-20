<?php

use MapasCulturais\i;

$this->import('
    conectaente--entity-form
    mc-avatar
    mc-confirm-button
    mc-icon
    mc-modal
    mc-tag-list
    mc-title
    select-entity
');
?>

<article class="panel__row panel-entity-card">
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
        <div v-if="!trashed" class="right">
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

                                <div v-if="!trashed" class="icon">
                                    <mc-confirm-button @confirm="removeSeal()">
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

                        <select-entity v-if="!trashed && !entity.seals.length" type="seal" openside="down-right" @select="addSeal($event)">
                            <template #button="{ toggle }">
                                <div class="entity-seals__seals--addSeal" @click="toggle()" v-tooltip="'<?= i::esc_attr__('Cadastrar selo') ?>'">
                                    <mc-icon name="add"></mc-icon>
                                </div>
                            </template>
                        </select-entity>

                        <mc-tag-list v-if="!trashed && !entity.seals.length" :tags="['<?= i::esc_attr__('Sem selo: nenhuma oportunidade é reconhecida') ?>']" classes="danger__background"></mc-tag-list>
                        <mc-tag-list v-if="!trashed && unusableSeals.length" :tags="['<?= i::esc_attr__('Selo indisponível: as oportunidades deixam de ser reconhecidas') ?>']" classes="danger__background"></mc-tag-list>
                    </div>
                </div>
            </div>

            <div class="col-6 sm:col-12 cardKey__private">
                <div class="cardKey__private--header">
                    <div class="label"><?= i::__('Token do CultBR:') ?></div>
                    <a v-if="!trashed" class="view" @click="toggleToken()"><mc-icon name="eye-view"></mc-icon></a>
                    <a v-if="!trashed" class="copy" @click="copyToken()"><mc-icon name="copy"></mc-icon></a>
                </div>
                <div class="cardKey__private--content"><span>{{ shownToken }}</span></div>
            </div>
        </div>
    </main>

    <footer class="panel-entity-card__footer">
        <div class="panel-entity-card__footer-actions">
            <div class="panel-entity-card__footer-actions left">
                <div class="panel__entity-actions">
                    <button v-if="trashed" class="button unpublish button--primary button--icon button-action recover" @click="askPassword('undelete')">
                        <?php i::_e('Recuperar') ?>
                    </button>
                    <button v-if="trashed" class="button button--text delete button--icon panel__entity-actions--trash" @click="askPassword('destroy')">
                        <mc-icon name="trash"></mc-icon>
                        <?php i::_e('Excluir permanentemente') ?>
                    </button>
                    <button v-else class="button button--text delete button--icon panel__entity-actions--trash" @click="askPassword('delete')">
                        <mc-icon name="trash"></mc-icon>
                        <?php i::_e('Excluir') ?>
                    </button>
                </div>
            </div>
        </div>
    </footer>

    <mc-modal ref="passwordModal" title="<?= i::esc_attr__('Confirme sua senha') ?>" classes="create-modal" @close="password = ''">
        <template #default>
            <div class="create-modal__fields">
                <div class="field">
                    <label><?php i::_e('Sua senha') ?></label>
                    <input type="password" v-model="password" autocomplete="current-password" @keyup.enter="confirmPassword()">
                    <p class="field__note">{{ passwordPrompt }}</p>
                </div>
            </div>
        </template>

        <template #actions="modal">
            <button class="button button--primary" @click="confirmPassword()"><?php i::_e('Confirmar') ?></button>
            <button class="button button--text button--text-del" @click="modal.close()"><?php i::_e('Cancelar') ?></button>
        </template>
    </mc-modal>
</article>
