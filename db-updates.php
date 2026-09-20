<?php

use function MapasCulturais\__table_exists;
use function MapasCulturais\__try;

return [
    'conectaente: cria as tabelas de ente federado' => function () {
        if (!__table_exists('conectaente_federative_entity')) {
            __try("CREATE SEQUENCE conectaente_federative_entity_id_seq INCREMENT BY 1 MINVALUE 1 START 1");

            __try("CREATE TABLE conectaente_federative_entity (
                id INT NOT NULL DEFAULT nextval('conectaente_federative_entity_id_seq'),
                name VARCHAR(255) NOT NULL,
                document VARCHAR(14) NOT NULL,
                token TEXT NOT NULL,
                create_timestamp TIMESTAMP(0) NOT NULL,
                update_timestamp TIMESTAMP(0) NULL,
                PRIMARY KEY(id)
            )");

            __try("CREATE UNIQUE INDEX unq_conectaente_federative_entity_document ON conectaente_federative_entity (document)");
            __try("CREATE UNIQUE INDEX unq_conectaente_federative_entity_token ON conectaente_federative_entity (token)");
        }

        if (!__table_exists('conectaente_federative_entity_seal')) {
            __try("CREATE SEQUENCE conectaente_federative_entity_seal_id_seq INCREMENT BY 1 MINVALUE 1 START 1");

            __try("CREATE TABLE conectaente_federative_entity_seal (
                id INT NOT NULL DEFAULT nextval('conectaente_federative_entity_seal_id_seq'),
                federative_entity_id INT NOT NULL,
                seal_id INT NOT NULL,
                create_timestamp TIMESTAMP(0) NOT NULL,
                PRIMARY KEY(id)
            )");

            __try("CREATE UNIQUE INDEX unq_conectaente_fe_seal_seal_id ON conectaente_federative_entity_seal (seal_id)");
            __try("CREATE UNIQUE INDEX unq_conectaente_fe_seal_entity_id ON conectaente_federative_entity_seal (federative_entity_id)");

            __try("ALTER TABLE conectaente_federative_entity_seal
                ADD CONSTRAINT fk_conectaente_fe_seal_entity
                FOREIGN KEY (federative_entity_id) REFERENCES conectaente_federative_entity(id) ON DELETE CASCADE");

            __try("ALTER TABLE conectaente_federative_entity_seal
                ADD CONSTRAINT fk_conectaente_fe_seal_seal
                FOREIGN KEY (seal_id) REFERENCES seal(id) ON DELETE CASCADE");
        }
    },

    'conectaente: um selo por ente federado' => function () {
        __try("DROP INDEX IF EXISTS idx_conectaente_fe_seal_entity_id");
        __try("CREATE UNIQUE INDEX IF NOT EXISTS unq_conectaente_fe_seal_entity_id ON conectaente_federative_entity_seal (federative_entity_id)");
    },
];
