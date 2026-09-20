<?php

use MapasCulturais\i;

$this->import('
    conectaente--entities-list
    conectaente--entity-form
    mc-icon
');

$json = fn(array $data) => json_encode(array_values($data), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>

<div class="panel-page">
    <header class="panel-page__header">
        <div class="panel-page__header-title">
            <div class="title">
                <div class="title__icon agent__background">
                    <mc-icon name="agent"></mc-icon>
                </div>
                <h1 class="title__title"> <?= i::_e('Entes Federados') ?> </h1>
            </div>
        </div>
        <p class="panel-page__header-subtitle">
            <?= i::_e('Cada Ente Federado é reconhecido pelo selo aplicado às oportunidades, e envia ao CultBR com o seu token.') ?>
        </p>
        <div class="panel-page__header-actions">
            <conectaente--entity-form></conectaente--entity-form>
        </div>
    </header>

    <conectaente--entities-list :entities='<?= $json($cards) ?>' :trashed='<?= $json($trashedCards) ?>'></conectaente--entities-list>
</div>
