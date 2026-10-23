<?php
/**
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use ConectaEnte\Services\PublicationRequirements;
use ConectaEnte\Vocabulary\AffirmativeAction;
use ConectaEnte\Vocabulary\AffirmativeActionGroup;

$this->jsObject['config']['conectaenteAffirmativeActions'] = [
    'options' => array_values(array_map(fn(AffirmativeAction $action) => [
        'value' => $action->value,
        'label' => $action->label(),
        'hasGroups' => $action->hasGroups(),
    ], array_filter(
        AffirmativeAction::cases(),
        fn(AffirmativeAction $action) => $action !== AffirmativeAction::NOT_PLANNED,
    ))),
    'groups' => array_map(fn(AffirmativeActionGroup $group) => [
        'value' => $group->value,
        'label' => $group->label(),
    ], AffirmativeActionGroup::cases()),
    'notPlanned' => [
        'value' => AffirmativeAction::NOT_PLANNED->value,
        'label' => AffirmativeAction::NOT_PLANNED->label(),
    ],
    'otherLegislation' => AffirmativeAction::OTHER_LEGISLATION->value,
    'descriptionMaxLength' => PublicationRequirements::OTHER_LEGISLATION_MAX_LENGTH,
];
