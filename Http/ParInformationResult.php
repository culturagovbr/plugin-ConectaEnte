<?php

namespace ConectaEnte\Http;

use ConectaEnte\Dto\ParInformation;

/**
 * Desfecho de `GET /api/v1/par-information`: árvore lida, API fora do ar,
 * token rejeitado, ou o caminho simplesmente não existe neste ambiente (o
 * contrato reduzido de produção pode não expor `par-information`). Essas
 * quatro variantes só são produzidas pelo `Client`, dentro do job que
 * sincroniza o cache — nunca durante a requisição do usuário.
 *
 * `unavailable()` é a única variante que o `ParInformationService` produz
 * sozinho: cache ainda vazio (job nunca rodou com sucesso para este ente),
 * distinto de "a API respondeu e este ente não tem dados" (isso vira uma
 * árvore `ok()` vazia, gravada no cache pelo job).
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

    public static function unreachable(): self
    {
        return new self(tree: null, unreachable: true, notFound: false);
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
