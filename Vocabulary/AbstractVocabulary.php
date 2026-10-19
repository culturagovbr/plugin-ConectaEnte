<?php

namespace ConectaEnte\Vocabulary;

use MapasCulturais\i;

/**
 * Base dos vocabulários: cada constante pública da subclasse é um valor aceito.
 * Substitui os enums nativos da v7, que o PHP 7.2 não compila.
 */
abstract class AbstractVocabulary
{
    /**
     * Valores aceitos, na ordem de declaração.
     *
     * @return string[]
     */
    public static function cases()
    {
        $values = [];

        foreach ((new \ReflectionClass(static::class))->getReflectionConstants() as $constant) {
            if ($constant->isPublic() && $constant->getDeclaringClass()->getName() === static::class) {
                $values[] = $constant->getValue();
            }
        }

        return $values;
    }

    /**
     * O próprio valor se ele está na lista; nulo caso contrário, sem coerção de tipo.
     *
     * @param mixed $value
     * @return string|null
     */
    public static function tryFrom($value)
    {
        return in_array($value, static::cases(), true) ? $value : null;
    }

    /**
     * Rótulo legível na língua da instalação; nulo para valor fora da lista, de propósito,
     * para falhar alto em vez de devolver a chave crua.
     *
     * @param mixed $value
     * @return string|null
     */
    public static function label($value)
    {
        $value = static::tryFrom($value);

        if ($value === null) {
            return null;
        }

        return self::translate(static::text($value));
    }

    /**
     * Texto fixo em pt-br do valor, o que vai ao payload quando ele é enviado por rótulo.
     *
     * @param string $value valor já validado
     * @return string
     */
    public static function text($value)
    {
        $texts = static::texts();

        return isset($texts[$value]) ? $texts[$value] : $value;
    }

    /**
     * Mapa valor → rótulo traduzido, para alimentar registerOpportunityMetadata.
     *
     * @return string[]
     */
    public static function options()
    {
        $options = [];

        foreach (static::cases() as $value) {
            $options[$value] = static::label($value);
        }

        return $options;
    }

    /**
     * Texto pt-br por valor, quando difere do próprio valor.
     *
     * @return string[]
     */
    protected static function texts()
    {
        return [];
    }

    private static function translate($text)
    {
        return class_exists(i::class) ? i::__($text) : $text;
    }
}
