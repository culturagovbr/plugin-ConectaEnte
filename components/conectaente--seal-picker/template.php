<?php

use MapasCulturais\i;

$this->import('
    mc-avatar
    mc-icon
    mc-popover
');
?>

<mc-popover openside="down-right" classes="select-entity__popover" @close="keyword = ''">
    <template #button="{ toggle }">
        <slot name="button" :toggle="toggle"></slot>
    </template>

    <template #default="{ close }">
        <div class="select-entity">
            <form class="select-entity__form" @submit.prevent>
                <input v-model="keyword" type="text" class="select-entity__form--input" placeholder="<?= i::esc_attr__('Pesquise por selos') ?>">
                <button type="button" class="select-entity__form--button">
                    <mc-icon name="search"></mc-icon>
                </button>
            </form>

            <p class="select-entity__description">
                <span v-if="matches.length"><?php i::_e('Selecione um dos selos') ?></span>
                <span v-else><?php i::_e('Nenhum selo com esse nome') ?></span>
            </p>

            <ul class="select-entity__results">
                <li v-for="seal in matches" :key="seal.id" class="select-entity__results--item seal" @click="select(seal, close)">
                    <span class="icon">
                        <mc-avatar :entity="seal" size="xsmall"></mc-avatar>
                    </span>
                    <span class="label">{{ seal.name }}</span>
                </li>
            </ul>
        </div>
    </template>
</mc-popover>
