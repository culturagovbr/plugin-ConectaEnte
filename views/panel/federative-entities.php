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
        <p><?php i::_e('Cada Ente Federado é reconhecido pelo selo aplicado às oportunidades, e envia ao CultBR com o seu token.'); ?></p>
        <button type="button" class="btn btn-primary add js-open-form"><?php i::_e('Cadastrar Ente Federado'); ?></button>
    </header>

    <?php if ($simulated): ?>
        <div class="alert warning conectaente-simulated">
            <?php i::_e('Modo simulado ativo — nenhum envio real será feito.'); ?>
            <small><?php i::_e('Defina CONECTAENTE_MODE=live para enviar de verdade.'); ?></small>
        </div>
    <?php endif; ?>

    <div class="conectaente-message" role="alert" hidden></div>

    <form class="conectaente-form" autocomplete="off" hidden>
        <h3 class="js-form-title"><?php i::_e('Cadastrar Ente Federado'); ?></h3>
        <input type="hidden" name="id" value="">

        <label>
            <?php i::_e('Nome do Ente Federado'); ?> <span class="required"><?php i::_e('*obrigatório'); ?></span>
            <input type="text" name="name" maxlength="255">
            <small><?php i::_e('O CNPJ não é digitado: vem da própria Plataforma CultBR ao verificar o token.'); ?></small>
        </label>

        <div class="conectaente-field">
            <span class="conectaente-label"><?php i::_e('Selo'); ?> <span class="required"><?php i::_e('*obrigatório'); ?></span></span>
            <input type="hidden" name="seal_id" value="">
            <button type="button" class="btn btn-default js-seal-toggle"
                    data-empty="<?php i::_e('Selecionar selo'); ?>"><?php i::_e('Selecionar selo'); ?></button>
            <div class="conectaente-seal-popover" hidden>
                <input type="search" class="js-seal-search" placeholder="<?php i::_e('Pesquise por selos'); ?>">
                <ul class="js-seal-list">
                    <?php foreach ($activeSeals as $id => $name): $used = in_array($id, $usedSealIds, true); ?>
                        <li>
                            <button type="button" data-id="<?= (int) $id ?>" data-name="<?= $e($name) ?>"
                                    <?= $used ? 'data-used="1" disabled' : '' ?>>
                                <?= $e($name) ?><?= $used ? ' <em>' . $e(i::__('(já em uso)')) . '</em>' : '' ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="js-seal-none" hidden><?php i::_e('Nenhum selo encontrado.'); ?></p>
            </div>
        </div>

        <label>
            <?php i::_e('Token do CultBR'); ?> <span class="required js-token-required"><?php i::_e('*obrigatório'); ?></span>
            <input type="password" name="token" autocomplete="new-password">
            <small class="js-token-hint" data-edit="<?php i::_e('Deixe em branco para manter o token atual.'); ?>"></small>
        </label>

        <button type="submit" class="btn btn-primary"><?php i::_e('Salvar'); ?></button>
        <button type="button" class="btn btn-default js-cancel"><?php i::_e('Cancelar'); ?></button>
    </form>

    <?php if ($entities): ?>
        <input type="search" class="conectaente-search js-search" placeholder="<?php i::_e('Buscar por nome ou CNPJ'); ?>">

        <ul class="conectaente-list">
        <?php foreach ($entities as $entity):
            $cnpj = CnpjFormatter::format($entity->getCnpj());
            $token = $tokens[$entity->getId()]; ?>
            <li class="conectaente-card" data-id="<?= (int) $entity->getId() ?>"
                data-name="<?= $e($entity->getName()) ?>"
                data-seal-id="<?= (int) $entity->getSealId() ?>"
                data-search="<?= $e(mb_strtolower($entity->getName() . ' ' . $cnpj . ' ' . $entity->getCnpj(), 'UTF-8')) ?>"
                data-url-update="<?= $e($app->createUrl('conecta-ente', 'update', [$entity->getId()])) ?>"
                data-url-remove="<?= $e($app->createUrl('conecta-ente', 'remove', [$entity->getId()])) ?>">
                <div class="conectaente-card-info">
                    <h3><?= $e($entity->getName()) ?></h3>
                    <p><strong><?php i::_e('CNPJ:'); ?></strong> <?= $e($cnpj) ?></p>
                    <p><strong><?php i::_e('SELO:'); ?></strong> <?= $e($activeSeals[$entity->getSealId()]) ?></p>
                    <p class="conectaente-token">
                        <?php i::_e('Token do CultBR:'); ?>
                        <?= $token['token_registered']
                            ? '<code>••••••••' . $e($token['token_last4']) . '</code>'
                            : $e(i::__('não cadastrado')) ?>
                    </p>
                    <?php if ($entity->createTimestamp): ?>
                        <p class="conectaente-date"><?= $e(i::__('Cadastrado em')) ?> <?= $e($entity->createTimestamp->format('d/m/Y H:i')) ?></p>
                    <?php endif; ?>
                </div>
                <div class="conectaente-actions">
                    <button type="button" class="btn btn-primary js-edit"><?php i::_e('Editar'); ?></button>
                    <button type="button" class="btn btn-danger js-remove"><?php i::_e('Excluir'); ?></button>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
        <p class="conectaente-empty js-search-none" hidden><?php i::_e('Nenhum ente encontrado.'); ?></p>
    <?php else: ?>
        <p class="conectaente-empty"><?php i::_e('Nenhum ente federado cadastrado.'); ?></p>
    <?php endif; ?>
</div>
