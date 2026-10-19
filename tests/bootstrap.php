<?php

// Bootstrap dos testes unitários do plugin: sem banco, sem HTTP e sem subir o App.
// Carrega o autoload do composer da v5 (PHPUnit 8, MapasCulturais\i) e o do namespace ConectaEnte\.

$plugin = dirname(__DIR__);
$protected = dirname(__DIR__, 4);

require $protected . '/vendor/autoload.php';

spl_autoload_register(function ($class) use ($plugin) {
    $prefix = 'ConectaEnte\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $file = $plugin . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// os testes ficam em tests/src com namespace Tests\ConectaEnte
spl_autoload_register(function ($class) use ($plugin) {
    $prefix = 'Tests\\ConectaEnte\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $file = $plugin . '/tests/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
