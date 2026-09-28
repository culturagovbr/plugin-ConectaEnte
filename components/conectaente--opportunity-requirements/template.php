<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use MapasCulturais\i;

$this->import('
    mc-icon
    mc-loading
');
?>
<div ref="root" class="conectaente-opportunity-requirements" :class="{ 'conectaente-opportunity-requirements--loading': loading }" aria-live="polite">
    <p v-if="failed" class="conectaente-opportunity-requirements__failed">
        <?php i::_e('Não foi possível carregar os campos pendentes.') ?>
    </p>
    <mc-loading v-else-if="!missing" :condition="true"></mc-loading>
    <p v-else-if="!fields.length" class="conectaente-opportunity-requirements__done">
        <?php i::_e('Nenhum campo pendente.') ?>
    </p>
    <template v-else>
        <section v-if="!openGroup" class="timeline conectaente-opportunity-requirements__timeline">
            <div v-for="group in groups" :key="group.name" class="item"
                :class="{
                    'conectaente-opportunity-requirements__item--active': group.name === activeGroup,
                    'conectaente-opportunity-requirements__item--done': !group.count,
                }">
                <div class="item__dot"><span class="dot"></span></div>
                <div class="item__content">
                    <button v-if="group.count" type="button" class="item__content--title conectaente-opportunity-requirements__link" @click="chooseGroup(group.name)">
                        {{ group.label }}
                        <span class="conectaente-opportunity-requirements__count">{{ group.count }}</span>
                    </button>
                    <span v-else class="item__content--title">
                        {{ group.label }}
                        <mc-icon name="check"></mc-icon>
                    </span>
                </div>
            </div>
        </section>
        <template v-else>
            <button type="button" class="conectaente-opportunity-requirements__back" @click="chooseGroup(null)">
                <mc-icon name="arrow-left"></mc-icon> <?php i::_e('Todos os campos') ?>
            </button>
            <section class="timeline conectaente-opportunity-requirements__timeline">
                <div v-for="field in openFields" :key="field.key" class="item" :class="{ 'conectaente-opportunity-requirements__item--active': field.active }">
                    <div class="item__dot"><span class="dot"></span></div>
                    <div class="item__content">
                        <button v-if="field.reachable" type="button" class="item__content--title conectaente-opportunity-requirements__link" @click="goToField(field)">
                            {{ field.label }}
                        </button>
                        <span v-else class="item__content--title">{{ field.label }}</span>
                        <div class="item__content--description">
                            <p v-for="message in field.messages" :key="message">{{ message }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </template>
</div>
