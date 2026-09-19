<?php

use MapasCulturais\i;

$this->import('panel--entity-tabs');
?>

<div class="panel-page">
    <header class="panel-page__header">
        <div class="panel-page__header-title">
            <div class="title">
                <div class="title__icon space__background"> <mc-icon name="space"></mc-icon> </div>
                <h1 class="title__title"> <?= i::_e('Entes Federados') ?> </h1>
            </div>
        </div>
        <p class="panel-page__header-subtitle">
            <?= i::_e('Nesta seção você pode adicionar e gerenciar todos os entes federados') ?>
        </p>
    </header>

    <panel--entity-tabs type="space"></panel--entity-tabs>
</div>
