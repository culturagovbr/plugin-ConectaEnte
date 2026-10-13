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
                status SMALLINT NOT NULL DEFAULT 1,
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

    'conectaente: lixeira do Ente Federado' => function () {
        __try("ALTER TABLE conectaente_federative_entity ADD COLUMN IF NOT EXISTS status SMALLINT NOT NULL DEFAULT 1");
    },

    'conectaente: copia os metadados do PAR para as chaves prefixadas' => function () {
        \ConectaEnte\Migrations\CopyLegacyParKeys::run();
    },

    'conectaente: cria as tabelas de histórico de envio' => function () {
        if (!__table_exists('conectaente_log')) {
            __try("CREATE SEQUENCE conectaente_log_id_seq INCREMENT BY 1 MINVALUE 1 START 1");

            // opportunity_id sem chave estrangeira: excluir a oportunidade não pode apagar nem travar o histórico
            __try("CREATE TABLE conectaente_log (
                id INT NOT NULL DEFAULT nextval('conectaente_log_id_seq'),
                uuid VARCHAR(36) NOT NULL,
                opportunity_id INT NOT NULL,
                par_edital_id INT NULL,
                outcome VARCHAR(20) NOT NULL,
                triggered_by_user_id INT NULL,
                create_timestamp TIMESTAMP(0) NOT NULL,
                update_timestamp TIMESTAMP(0) NULL,
                PRIMARY KEY(id)
            )");

            __try("CREATE UNIQUE INDEX unq_conectaente_log_uuid ON conectaente_log (uuid)");
            __try("CREATE INDEX idx_conectaente_log_opportunity ON conectaente_log (opportunity_id, create_timestamp DESC)");

            __try("ALTER TABLE conectaente_log
                ADD CONSTRAINT fk_conectaente_log_user
                FOREIGN KEY (triggered_by_user_id) REFERENCES usr(id) ON DELETE SET NULL");
        }

        if (!__table_exists('conectaente_log_attempt')) {
            __try("CREATE SEQUENCE conectaente_log_attempt_id_seq INCREMENT BY 1 MINVALUE 1 START 1");

            __try("CREATE TABLE conectaente_log_attempt (
                id INT NOT NULL DEFAULT nextval('conectaente_log_attempt_id_seq'),
                log_id INT NOT NULL,
                attempt SMALLINT NOT NULL,
                max_attempts SMALLINT NOT NULL,
                endpoint TEXT NOT NULL,
                http_method VARCHAR(10) NOT NULL,
                http_status SMALLINT NULL,
                request_id VARCHAR(255) NULL,
                request_payload TEXT NULL,
                response_body TEXT NULL,
                response_headers TEXT NULL,
                error_message TEXT NULL,
                outcome VARCHAR(20) NOT NULL,
                sent_at TIMESTAMP(0) NOT NULL,
                duration_ms INT NULL,
                PRIMARY KEY(id)
            )");

            __try("CREATE INDEX idx_conectaente_log_attempt_log ON conectaente_log_attempt (log_id, id)");

            __try("ALTER TABLE conectaente_log_attempt
                ADD CONSTRAINT fk_conectaente_log_attempt_log
                FOREIGN KEY (log_id) REFERENCES conectaente_log(id) ON DELETE CASCADE");
        }
    },
];
