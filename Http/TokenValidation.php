<?php

namespace ConectaEnte\Http;

use MapasCulturais\i;

class TokenValidation
{
    private $accepted;
    private $unavailable;
    private $document;
    private $entityName;
    private $message;

    private function __construct($accepted, $unavailable, $document = null, $entityName = null, $message = null)
    {
        $this->accepted = $accepted;
        $this->unavailable = $unavailable;
        $this->document = $document;
        $this->entityName = $entityName;
        $this->message = $message;
    }

    public static function accept($document, $entityName)
    {
        return new self(true, false, $document, $entityName);
    }

    public static function reject($message)
    {
        return new self(false, false, null, null, $message);
    }

    public static function unreachable()
    {
        $message = class_exists(i::class)
            ? i::__('Não foi possível falar com a Plataforma CultBR agora. Tente de novo em alguns minutos.')
            : 'Não foi possível falar com a Plataforma CultBR agora. Tente de novo em alguns minutos.';

        return new self(false, true, null, null, $message);
    }

    /**
     * `CONECTAENTE_HOST` não declarado em modo live: problema da instalação, não do token nem da API.
     */
    public static function misconfigured()
    {
        $message = 'A integração com a Plataforma CultBR não está configurada (CONECTAENTE_HOST). Avise o responsável pela instalação.';

        return new self(false, true, null, null, class_exists(i::class) ? i::__($message) : $message);
    }

    public function isAccepted()
    {
        return $this->accepted;
    }

    public function isUnavailable()
    {
        return $this->unavailable;
    }

    public function getDocument()
    {
        return $this->document;
    }

    public function getEntityName()
    {
        return $this->entityName;
    }

    public function getMessage()
    {
        return $this->message;
    }
}
