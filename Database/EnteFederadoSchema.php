<?php

namespace ConectaEnte\Database;

/**
 * SQL da tabela de entes federados. Fica fora do db-updates.php para poder ser conferido por teste
 * sem subir o App.
 */
class EnteFederadoSchema
{
    const TABLE = 'conectaente_ente_federado';
    const SEQUENCE = 'conectaente_ente_federado_id_seq';
    const UNIQUE_SEAL = 'conectaente_ente_federado_seal_id_uq';
    const FK_SEAL = 'conectaente_ente_federado_seal_fk';

    public static function createSequence()
    {
        return 'CREATE SEQUENCE ' . self::SEQUENCE . ' INCREMENT BY 1 MINVALUE 1 START 1';
    }

    // a FK com CASCADE faz o ente sair junto quando o selo é excluído (só admin exclui selo)
    public static function createTable()
    {
        return 'CREATE TABLE ' . self::TABLE . ' (
            id INT NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            seal_id INT NOT NULL,
            token TEXT NOT NULL,
            cnpj VARCHAR(14) DEFAULT NULL,
            create_timestamp TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            update_timestamp TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id),
            CONSTRAINT ' . self::UNIQUE_SEAL . ' UNIQUE (seal_id),
            CONSTRAINT ' . self::FK_SEAL . ' FOREIGN KEY (seal_id) REFERENCES seal (id)
                ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        )';
    }
}
