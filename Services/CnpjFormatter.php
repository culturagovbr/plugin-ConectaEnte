<?php

namespace ConectaEnte\Services;

class CnpjFormatter
{
    /**
     * `00.000.000/0000-00` para 14 dígitos; qualquer outro valor volta como veio, para a tela nunca
     * esconder um dado estranho.
     */
    public static function format($cnpj)
    {
        $digits = (string) $cnpj;

        if (!preg_match('/^\d{14}\z/', $digits)) {
            return $digits;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2)
        );
    }
}
