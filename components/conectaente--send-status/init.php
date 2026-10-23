<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use ConectaEnte\Plugin;
use MapasCulturais\Entities\Opportunity;

$opportunity = $this->controller->requestedEntity ?? null;

$this->jsObject['config']['conectaenteSendStatus'] = Plugin::instance()->sendStatusView()->of(
    $opportunity instanceof Opportunity ? $opportunity : null,
);
