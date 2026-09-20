<?php

use MapasCulturais\i;

$this->import('
    mc-icon
    mc-modal
    conectaente--seal-picker
');
?>

<mc-modal :title="title" classes="create-modal conectaente--entity-form" @open="reset()">
    <template #default>
        <div class="create-modal__fields">
            <div v-if="!entity" class="field" :class="{ error: errors.name }">
                <label class="field__title"><?php i::_e('Nome do Ente Federado') ?> <span class="required">*<?php i::_e('obrigatório') ?></span></label>
                <input type="text" v-model="name" @input="clearError('name')">
                <small class="field__description"><?php i::_e('O CNPJ não é digitado: vem da própria Plataforma CultBR ao verificar o token.') ?></small>
                <small v-if="errors.name" class="field__error">{{ errors.name.join('; ') }}</small>
            </div>

            <div v-if="!entity" class="field" :class="{ error: errors.seal }">
                <label class="field__title"><?php i::_e('Selo') ?> <span class="required">*<?php i::_e('obrigatório') ?></span></label>
                <conectaente--seal-picker :seals="catalog.seals" @select="selectSeal($event)">
                    <template #button="{ toggle }">
                        <button type="button" class="button button--primary-outline button--icon" @click="toggle()">
                            <mc-icon name="seal"></mc-icon>
                            <span v-if="seal">{{ seal.name }}</span>
                            <span v-else><?php i::_e('Selecionar selo') ?></span>
                        </button>
                    </template>
                </conectaente--seal-picker>
                <small v-if="errors.seal" class="field__error">{{ errors.seal.join('; ') }}</small>
            </div>

            <div class="field" :class="{ error: errors.token }">
                <label v-if="entity" class="field__title"><?php i::_e('Novo token') ?></label>
                <label v-else class="field__title"><?php i::_e('Token do CultBR') ?> <span class="required">*<?php i::_e('obrigatório') ?></span></label>
                <input type="password" v-model="token" autocomplete="off" @input="clearError('token')">
                <small v-if="entity" class="field__description"><?php i::_e('Deixe em branco para manter o token atual.') ?></small>
                <small v-if="errors.token" class="field__error">{{ errors.token.join('; ') }}</small>
            </div>
        </div>
    </template>

    <template #button="modal">
        <button v-if="entity" class="button button--primary button--icon" @click="modal.open()">
            <mc-icon name="edit"></mc-icon> <?php i::_e('Editar') ?>
        </button>
        <button v-else class="button button--primary button--icon" @click="modal.open()">
            <mc-icon name="add"></mc-icon> <?php i::_e('Cadastrar Ente Federado') ?>
        </button>
    </template>

    <template #actions="modal">
        <button class="button button--primary" @click="save(modal)"><?php i::_e('Salvar') ?></button>
        <button class="button button--text button--text-del" @click="modal.close()"><?php i::_e('Cancelar') ?></button>
    </template>
</mc-modal>
