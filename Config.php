<?php

namespace ConectaEnte;

use MapasCulturais\App;

/**
 * Configuração do plugin. Cada chave pode vir de:
 *   1. $app->config['conectaente.<chave>']  (config.d da instalação)
 *   2. variável de ambiente CONECTAENTE_<CHAVE>
 *   3. valor padrão abaixo
 *
 * Nada aqui exige host ou token para o plugin subir (modo simulado).
 */
class Config
{
    const MODE_DEV  = 'dev';
    const MODE_LIVE = 'live';

    /**
     * Qualquer valor diferente de "live" é tratado como simulado, para um
     * erro de digitação nunca ligar o envio real.
     */
    public static function mode()
    {
        $mode = strtolower(trim((string) self::get('mode', self::MODE_DEV)));

        return $mode === self::MODE_LIVE ? self::MODE_LIVE : self::MODE_DEV;
    }

    public static function isLive()
    {
        return self::mode() === self::MODE_LIVE;
    }

    /**
     * Host da Conecta Ente. O contrato não declara "servers"; sem padrão.
     */
    public static function host()
    {
        return rtrim((string) self::get('host', ''), '/');
    }

    public static function get($key, $default = null)
    {
        $app = App::i();

        $config_key = 'conectaente.' . $key;
        if (isset($app->config[$config_key])) {
            return $app->config[$config_key];
        }

        $env = getenv('CONECTAENTE_' . strtoupper($key));
        if ($env !== false && $env !== '') {
            return $env;
        }

        return $default;
    }
}
