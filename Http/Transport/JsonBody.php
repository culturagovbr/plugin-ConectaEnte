<?php

namespace ConectaEnte\Http\Transport;

final class JsonBody
{
    public static function encode(array $body): string
    {
        // sem a flag, UTF-8 inválido vira corpo vazio e um bug local chega como recusa do CultBR
        return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
