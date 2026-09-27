<?php
/**
 * @var MapasCulturais\Themes\BaseV2\Theme $this
 */

use ConectaEnte\Vocabulary\RegistrationChannel;

$this->jsObject['config']['conectaenteRegistrationChannels'] = [
    'channels' => array_map(fn(RegistrationChannel $channel) => [
        'value' => $channel->value,
        'label' => $channel->label(),
    ], RegistrationChannel::cases()),
    'emailValue' => RegistrationChannel::EMAIL->value,
];
