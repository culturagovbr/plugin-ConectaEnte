<?php

namespace ConectaEnte\Http;

use ConectaEnte\Dto\ParInformation;

/**
 * Desfecho de `GET /api/v1/par-information`; `unavailable()` é do cache vazio, não da API.
 */
final class ParInformationResult
{
    private function __construct(
        public readonly ?ParInformation $tree,
        public readonly bool $unreachable,
        public readonly bool $notFound,
        public readonly bool $unavailable = false,
        public readonly ?string $message = null,
    ) {
    }

    public static function ok(ParInformation $tree): self
    {
        return new self(tree: $tree, unreachable: false, notFound: false);
    }

    public static function rejected(string $message): self
    {
        return new self(tree: null, unreachable: false, notFound: false, message: $message);
    }

    public static function unreachable(?string $message = null): self
    {
        return new self(tree: null, unreachable: true, notFound: false, message: $message);
    }

    public static function notFound(): self
    {
        return new self(tree: null, unreachable: false, notFound: true);
    }

    public static function unavailable(): self
    {
        return new self(tree: null, unreachable: false, notFound: false, unavailable: true);
    }
}
