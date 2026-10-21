<?php

use ConectaEnte\Database\EnteFederadoSchema;

use function MapasCulturais\__exec;
use function MapasCulturais\__sequence_exists;
use function MapasCulturais\__table_exists;

// as chaves são globais na aplicação (migração de mesmo nome é descartada): sempre com prefixo conectaente
return [
    'create table conectaente_ente_federado' => function () {
        if (!__sequence_exists(EnteFederadoSchema::SEQUENCE)) {
            __exec(EnteFederadoSchema::createSequence());
        }

        if (!__table_exists(EnteFederadoSchema::TABLE)) {
            __exec(EnteFederadoSchema::createTable());
        }
    },
];
