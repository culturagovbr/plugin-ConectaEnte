<?php

use Symfony\Component\Cache\Adapter\ArrayAdapter;

// o core zera com NullAdapter; o fluxo do PAR grava e lê cache de verdade, então a suíte precisa de um que exista
return [
    'app.mscache' => new ArrayAdapter(),
];
