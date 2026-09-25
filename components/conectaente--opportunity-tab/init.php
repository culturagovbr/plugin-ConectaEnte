<?php
/**
 * @var MapasCulturais\App $app
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use ConectaEnte\Entities\FederativeEntitySeal;
use ConectaEnte\Vocabulary\ProponentType;

$this->jsObject['config']['conectaenteOpportunityTab'] = [
    'federativeSealIds' => $app->repo(FederativeEntitySeal::class)->findSealIdsOfEnabledEntities(),
    'legalEntityLabel' => ProponentType::LEGAL_ENTITY_LABEL,
];
