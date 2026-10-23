<?php

namespace ConectaEnte\Http;

use MapasCulturais\i;

/**
 * Desfecho da verificação de um token: aceito, recusado com motivo, ou API indisponível.
 */
final class TokenValidation
{
    private function __construct(
        public readonly bool $accepted,
        public readonly bool $unavailable,
        public readonly ?string $document = null,
        public readonly ?string $entityName = null,
        public readonly ?string $message = null,
    ) {
    }

    public static function accept(string $document, ?string $entityName): self
    {
        return new self(accepted: true, unavailable: false, document: $document, entityName: $entityName);
    }

    public static function reject(string $message): self
    {
        return new self(accepted: false, unavailable: false, message: $message);
    }

    public static function unreachable(): self
    {
        return new self(
            accepted: false,
            unavailable: true,
            message: i::__('Não foi possível falar com a Plataforma CultBR agora. Tente de novo em alguns minutos.'),
        );
    }
}
