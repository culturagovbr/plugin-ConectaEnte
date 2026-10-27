<?php

use ConectaEnte\Services\CnpjFormatter;
use MapasCulturais\i;

$this->layout = 'panel';

$e = function ($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
};
?>
<div class="panel-list panel-main-content conectaente" id="conectaente"
     data-url-create="<?= $e($app->createUrl('conecta-ente', 'create')) ?>"
     data-url-verify="<?= $e($app->createUrl('conecta-ente', 'verifyToken')) ?>">

    <header class="panel-header clearfix">
        <h2><?php i::_e('Entes Federados'); ?></h2>
    </header>

    <?php if ($simulated): ?>
        <div class="alert warning conectaente-simulated">
            <?php i::_e('Modo simulado ativo — nenhum envio real será feito.'); ?>
            <small><?php i::_e('Defina CONECTAENTE_MODE=live para enviar de verdade.'); ?></small>
        </div>
    <?php endif; ?>

    <div class="conectaente-message" role="alert" hidden></div>

    <?php if ($entities): ?>
        <table class="conectaente-table">
            <thead>
                <tr>
                    <th><?php i::_e('Nome'); ?></th>
                    <th><?php i::_e('Selo'); ?></th>
                    <th><?php i::_e('CNPJ'); ?></th>
                    <th><?php i::_e('Token'); ?></th>
                    <th><?php i::_e('Cadastrado em'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($entities as $entity): ?>
                <tr data-id="<?= (int) $entity->getId() ?>"
                    data-name="<?= $e($entity->getName()) ?>"
                    data-seal-id="<?= (int) $entity->getSealId() ?>"
                    data-url-update="<?= $e($app->createUrl('conecta-ente', 'update', [$entity->getId()])) ?>"
                    data-url-remove="<?= $e($app->createUrl('conecta-ente', 'remove', [$entity->getId()])) ?>">
                    <td><?= $e($entity->getName()) ?></td>
                    <td><?= $e($activeSeals[$entity->getSealId()]) ?></td>
                    <td><?= $e(CnpjFormatter::format($entity->getCnpj())) ?></td>
                    <td><?php i::_e('cadastrado'); ?></td>
                    <td><?= $entity->createTimestamp ? $e($entity->createTimestamp->format('d/m/Y H:i')) : '' ?></td>
                    <td class="conectaente-actions">
                        <button type="button" class="btn btn-default js-edit"><?php i::_e('Editar'); ?></button>
                        <button type="button" class="btn btn-danger js-remove"><?php i::_e('Remover'); ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="conectaente-empty"><?php i::_e('Nenhum ente federado cadastrado.'); ?></p>
    <?php endif; ?>

    <form class="conectaente-form" autocomplete="off">
        <h3 class="js-form-title"><?php i::_e('Novo ente federado'); ?></h3>
        <input type="hidden" name="id" value="">

        <label>
            <?php i::_e('Token'); ?>
            <input type="password" name="token" autocomplete="new-password">
            <small class="js-token-hint" data-new="<?php i::_e('Informe o token do ente.'); ?>"
                   data-edit="<?php i::_e('Deixe em branco para manter o token atual.'); ?>"></small>
        </label>

        <label>
            <?php i::_e('Nome'); ?>
            <input type="text" name="name" maxlength="255">
        </label>

        <label>
            <?php i::_e('Selo'); ?>
            <select name="seal_id">
                <option value=""><?php i::_e('Escolha um selo'); ?></option>
                <?php foreach ($activeSeals as $id => $name): ?>
                    <option value="<?= (int) $id ?>"
                        <?= in_array($id, $usedSealIds, true) ? 'disabled data-used="1"' : '' ?>>
                        <?= $e($name) ?><?= in_array($id, $usedSealIds, true) ? ' ' . $e(i::__('(já em uso)')) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit" class="btn btn-primary"><?php i::_e('Salvar'); ?></button>
        <button type="button" class="btn btn-default js-cancel" hidden><?php i::_e('Cancelar edição'); ?></button>
    </form>
</div>
