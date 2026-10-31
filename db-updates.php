<?php

use ConectaEnte\Database\FederativeEntitySchema;

use function MapasCulturais\__column_exists;
use function MapasCulturais\__exec;
use function MapasCulturais\__sequence_exists;
use function MapasCulturais\__table_exists;

// as chaves são globais na aplicação (migração de mesmo nome é descartada): sempre com prefixo conectaente
return [
    // vem antes da criação: quem já tem a tabela com nome em português a renomeia em vez de criar outra
    'conectaente: rename ente_federado table to federative_entity' => function () {
        if (__table_exists(FederativeEntitySchema::LEGACY_TABLE) && !__table_exists(FederativeEntitySchema::TABLE)) {
            foreach (FederativeEntitySchema::renameLegacyStatements() as $sql) {
                __exec($sql);
            }
        }

        // a coluna `nome` também já existiu: renomeia onde a tabela a tenha
        if (__column_exists(FederativeEntitySchema::TABLE, 'nome') && !__column_exists(FederativeEntitySchema::TABLE, 'name')) {
            __exec('ALTER TABLE ' . FederativeEntitySchema::TABLE . ' RENAME COLUMN nome TO name');
        }
    },

    'conectaente: create federative_entity table' => function () {
        if (!__sequence_exists(FederativeEntitySchema::SEQUENCE)) {
            __exec(FederativeEntitySchema::createSequence());
        }

        if (!__table_exists(FederativeEntitySchema::TABLE)) {
            __exec(FederativeEntitySchema::createTable());
        }
    },
];
