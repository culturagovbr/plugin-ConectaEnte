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
                </div>

                <select-entity v-if="!trashed && !entity.seals.length" type="seal" openside="down-right" @select="addSeal($event)">
                    <template #button="{ toggle }">
                        <div class="entity-seals__seals--addSeal" @click="toggle()" v-tooltip="'<?= i::esc_attr__('Cadastrar selo') ?>'">
                            <mc-icon name="add"></mc-icon>
                        </div>
                    </template>
                </select-entity>

                <div v-if="trashed && !entity.seals.length" class="entity-seals__seals--seal">
                    <div class="seal-icon"><mc-icon name="seal"></mc-icon></div>
                </div>
            </div>

            <div class="panel-entity-card__header--info">
                <mc-title tag="h2" :shortLength="100" :longLength="110">{{ entity.name }}</mc-title>
                <p class="panel-entity-card__header--info-subtitle"><strong><?php i::_e('CNPJ:') ?></strong> {{ entity.document }}</p>
                <p v-for="seal in entity.seals" :key="seal.id" class="panel-entity-card__header--info-subtitle"><strong><?php i::_e('Selo:') ?></strong> {{ seal.name }}</p>
            </div>
        </div>

        <div class="right">
            <template v-if="trashed">
                <mc-confirm-button @confirm="run('undelete')" button-class="button--primary button--icon" title="<?= i::esc_attr__('Recuperar Ente Federado') ?>" yes="<?= i::esc_attr__('Recuperar') ?>" no="<?= i::esc_attr__('Cancelar') ?>">
                    <template #message>
                        <p><?php i::_e('Você está recuperando') ?> <strong>{{ entity.name }}</strong> <?php i::_e('da lixeira.') ?></p>
                        <p><?php i::_e('Ele volta à listagem e à integração com o CultBR.') ?></p>
                    </template>
                    <?php i::_e('Recuperar') ?>
                </mc-confirm-button>
                <mc-confirm-button @confirm="run('destroy')" button-class="button--text delete button--icon panel__entity-actions--trash" title="<?= i::esc_attr__('Excluir permanentemente') ?>" yes="<?= i::esc_attr__('Excluir permanentemente') ?>" no="<?= i::esc_attr__('Cancelar') ?>">
                    <template #message>
                        <p><?php i::_e('Você está apagando de vez') ?> <strong>{{ entity.name }}</strong>.</p>
                        <p><?php i::_e('Somem o vínculo com o selo e o token.') ?> <strong class="danger__color"><?php i::_e('Não dá para desfazer.') ?></strong></p>
                    </template>
                    <mc-icon name="trash"></mc-icon>
                    <?php i::_e('Excluir permanentemente') ?>
                </mc-confirm-button>
            </template>
            <template v-else>
                <conectaente--entity-form :entity="entity"></conectaente--entity-form>
                <mc-confirm-button @confirm="run('delete')" button-class="button--text delete button--icon panel__entity-actions--trash" title="<?= i::esc_attr__('Excluir Ente Federado') ?>" yes="<?= i::esc_attr__('Excluir') ?>" no="<?= i::esc_attr__('Cancelar') ?>">
                    <template #message>
                        <p><?php i::_e('Você está excluindo') ?> <strong>{{ entity.name }}</strong>.</p>
                        <p><?php i::_e('Ele vai para a lixeira e') ?> <strong class="danger__color"><?php i::_e('deixa de ser integrado com o CultBR') ?></strong>. <?php i::_e('CNPJ, selo e token ficam reservados até você recuperá-lo ou excluí-lo permanentemente.') ?></p>
                    </template>
                    <mc-icon name="trash"></mc-icon>
                    <?php i::_e('Excluir') ?>
                </mc-confirm-button>
            </template>
        </div>
    </header>

    <main class="panel-entity-card__main">
        <div class="cardKey__private">
            <div class="cardKey__private--header">
                <div class="label"><?= i::__('Token do CultBR:') ?></div>
                <a v-if="!trashed" class="view" @click="toggleToken()"><mc-icon name="eye-view"></mc-icon></a>
                <a v-if="!trashed" class="copy" @click="run('copy')"><mc-icon name="copy"></mc-icon></a>
            </div>
            <div class="cardKey__private--content"><span>{{ shownToken }}</span></div>
        </div>

        <mc-tag-list v-if="!trashed && !entity.seals.length" :tags="['<?= i::esc_attr__('Ente Federado sem selo. Não será integrado com o CultBR.') ?>']" classes="danger__background"></mc-tag-list>
        <mc-tag-list v-if="!trashed && unusableSeals.length" :tags="['<?= i::esc_attr__('Selo indisponível. O Ente Federado não será integrado com o CultBR.') ?>']" classes="danger__background"></mc-tag-list>
        <mc-tag-list v-if="!trashed && sealWithValidity" :tags="[sealValidityWarning]" classes="danger__background"></mc-tag-list>
    </main>

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
