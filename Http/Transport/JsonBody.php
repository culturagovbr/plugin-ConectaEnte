<?php

namespace ConectaEnte\Http\Transport;

use RuntimeException;

/**
 * JSON_THROW_ON_ERROR não existe em PHP 7.2: a checagem é manual via json_last_error().
 */
class JsonBody
{
    public static function encode(array $body)
    {
        // sem UNESCAPED_UNICODE, acento vira \uXXXX; sem a checagem de erro, UTF-8 inválido
        // vira corpo vazio e um bug local chega como recusa do CultBR
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);

        if ($json === false || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(self::blamed($body, json_last_error_msg()));
        }

        return $json;
    }

    // a mensagem do json_encode não diz a chave: achá-la custa uma passada, e só no caminho do erro
    private static function blamed(array $body, $errorMessage)
    {
        foreach ($body as $key => $value) {
            if (!self::encodable($value)) {
                return "payload não serializável: {$errorMessage} (campo {$key})";
            }
        }

        return "payload não serializável: {$errorMessage}";
    }

    private static function encodable($value)
    {
        json_encode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
