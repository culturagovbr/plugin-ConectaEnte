<?php

namespace ConectaEnte\Dto;

use JsonSerializable;
use MapasCulturais\Entities\Seal;

/**
 * Um selo como o seletor da tela o conhece: id, nome e avatar no formato que o mc-avatar lê.
 */
final class SealOption implements JsonSerializable
{
    public function __construct(private Seal $seal)
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->seal->id,
            'name' => $this->seal->name,
            'files' => ['avatar' => self::avatar($this->seal)],
        ];
    }

    static function avatar(Seal $seal): ?array
    {
        $avatar = $seal->avatar?->transform('avatarMedium');

        return $avatar ? ['transformations' => ['avatarMedium' => ['url' => $avatar->url]]] : null;
    }
}
