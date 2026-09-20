<?php

use MapasCulturais\i;

$this->import('
    conectaente--entities-list
    mc-icon
');

$listedEntities = array_map(fn($federativeEntity) => [
    'id' => $federativeEntity->id,
    'name' => $federativeEntity->name,
    'document' => $federativeEntity->formattedDocument,
    'seals' => array_map(fn($link) => [
        'id' => $link->seal->id,
        'name' => $link->seal->name,
        'usable' => $link->isSealUsable(),
    ], $sealsByEntity[$federativeEntity->id] ?? []),
], $federativeEntities);
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
            <?= i::_e('Cada ente federado é reconhecido pelo selo aplicado às oportunidades, e envia ao CultBR com o seu token.') ?>
        </p>
    </header>

    <conectaente--entities-list :entities='<?= json_encode(array_values($listedEntities), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>'></conectaente--entities-list>
</div>
