<?php

namespace ConectaEnte\Dto;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Entities\FederativeEntitySeal;
use JsonSerializable;

/**
 * O que a listagem do painel recebe de um ente. O token só sai mascarado.
 */
final class FederativeEntityCard implements JsonSerializable
{
    const VISIBLE_PREFIX = 6;

    /** @param FederativeEntitySeal[] $sealLinks */
    public function __construct(private FederativeEntity $federativeEntity, private array $sealLinks)
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->federativeEntity->id,
            'name' => $this->federativeEntity->name,
            'document' => $this->federativeEntity->formattedDocument,
            'token' => self::mask($this->federativeEntity->token),
            'seals' => array_map([$this, 'seal'], array_values($this->sealLinks)),
        ];
    }

    private static function mask(string $token): string
    {
        $length = mb_strlen($token);

        if ($length <= self::VISIBLE_PREFIX) {
            return str_repeat('*', $length);
        }

        return mb_substr($token, 0, self::VISIBLE_PREFIX) . str_repeat('*', $length - self::VISIBLE_PREFIX);
    }

    private function seal(FederativeEntitySeal $link): array
    {
        $avatar = $link->seal->avatar?->transform('avatarMedium');

        return [
            'id' => $link->seal->id,
            'name' => $link->seal->name,
            'usable' => $link->isSealUsable(),
            'validity' => (int) $link->seal->validPeriod,
            'files' => ['avatar' => $avatar ? ['transformations' => ['avatarMedium' => ['url' => $avatar->url]]] : null],
        ];
    }
}
