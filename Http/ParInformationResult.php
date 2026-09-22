<?php

namespace ConectaEnte\Http;

use ConectaEnte\Dto\ParInformation;

/**
 * Desfecho de `GET /api/v1/par-information`: árvore lida, API fora do ar,
 * token rejeitado, ou o caminho simplesmente não existe neste ambiente (o
 * contrato reduzido de produção pode não expor `par-information`).
 */
final class ParInformationResult
{
    private function __construct(
        public readonly ?ParInformation $tree,
        public readonly bool $unreachable,
        public readonly bool $notFound,
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

    public static function unreachable(): self
    {
        return new self(tree: null, unreachable: true, notFound: false);
    }

    public static function notFound(): self
    {
        return new self(tree: null, unreachable: false, notFound: true);
    }
}
