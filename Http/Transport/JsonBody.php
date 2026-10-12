<?php

namespace ConectaEnte\Http\Transport;

use JsonException;

final class JsonBody
{
    public static function encode(array $body): string
    {
        try {
            // sem a flag, UTF-8 inválido vira corpo vazio e um bug local chega como recusa do CultBR
            return json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $error) {
            throw new JsonException(self::blamed($body, $error), $error->getCode(), $error);
        }
    }

    // a mensagem do json_encode não diz a chave: achá-la custa uma passada, e só no caminho do erro
    private static function blamed(array $body, JsonException $error): string
    {
        foreach ($body as $key => $value) {
            if (!self::encodable($value)) {
                return "{$error->getMessage()} (campo {$key})";
            }
        }

        return $error->getMessage();
    }

    private static function encodable(mixed $value): bool
    {
        try {
            json_encode($value, JSON_THROW_ON_ERROR);

            return true;
        } catch (JsonException) {
            return false;
        }
    }
}
