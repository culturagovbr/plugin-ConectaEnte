<?php

namespace ConectaEnte\Vocabulary;

use MapasCulturais\i;

/**
 * Opções sintéticas dos campos de direcionamento: não existem no vocabulário do campo, mas entram na
 * lista de escolha. O valor gravado é fixo; o que vai ao payload é o texto (`text()`).
 * Mesmos valores gravados da v7, para os dados serem compatíveis.
 */
class TargetingOption
{
    const NOT_TARGETED = '__edital_nao_se_direciona__';
    const ALL_OPTIONS = '__todas_opcoes__';

    /**
     * @return string[]
     */
    public static function cases()
    {
        return [self::NOT_TARGETED, self::ALL_OPTIONS];
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function isSynthetic($value)
    {
        return in_array($value, self::cases(), true);
    }

    /**
     * Texto fixo em pt-br da opção no campo informado, o que vai ao payload.
     *
     * @param string $option um valor de `cases()`
     * @param string $field  um valor de TargetingField
     *
     * @return string
     */
    public static function text($option, $field)
    {
        if ($option === self::NOT_TARGETED) {
            return TargetingField::notTargetedText($field);
        }

        if ($option === self::ALL_OPTIONS) {
            return 'Todas as opções';
        }

        throw new \InvalidArgumentException(sprintf('Opção sintética desconhecida: "%s".', $option));
    }

    /**
     * Texto exibido na tela, na língua da instalação.
     *
     * @param string $option
     * @param string $field
     *
     * @return string
     */
    public static function label($option, $field)
    {
        $text = self::text($option, $field);

        return class_exists(i::class) ? i::__($text) : $text;
    }
}
