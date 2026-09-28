<?php
/**
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use ConectaEnte\Vocabulary\LegalQuota;

$this->jsObject['config']['conectaenteQuotaReservation'] = [
    'legalQuotas' => array_map(fn(LegalQuota $quota) => $quota->text(), [
        LegalQuota::BLACK_PEOPLE,
        LegalQuota::INDIGENOUS_PEOPLE,
        LegalQuota::PEOPLE_WITH_DISABILITIES,
    ]),
    'openCompetition' => LegalQuota::OPEN_COMPETITION->text(),
    'labels' => array_combine(
        array_map(fn(LegalQuota $quota) => $quota->text(), LegalQuota::cases()),
        array_map(fn(LegalQuota $quota) => $quota->label(), LegalQuota::cases()),
    ),
];
