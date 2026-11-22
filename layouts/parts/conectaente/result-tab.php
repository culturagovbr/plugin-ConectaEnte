<?php
/**
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use ConectaEnte\Plugin;
use MapasCulturais\Entities\Opportunity;

$opportunity = $this->controller->requestedEntity ?? null;

if (!$opportunity instanceof Opportunity || $opportunity->parent || !$opportunity->canUser('@control') || !Plugin::instance()->sealedOpportunity()->isSealed($opportunity)) {
    return;
}

$this->import('conectaente--result-tab');
?>
<conectaente--result-tab :entity="entity"></conectaente--result-tab>
