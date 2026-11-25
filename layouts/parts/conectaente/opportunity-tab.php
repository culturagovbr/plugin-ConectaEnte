<?php
/**
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

$this->import('conectaente--opportunity-tab');
?>
<conectaente--opportunity-tab :entity="entity">
    <?php $this->part('conectaente/result-tab') ?>
</conectaente--opportunity-tab>
