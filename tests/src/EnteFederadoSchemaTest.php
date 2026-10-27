<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Database\EnteFederadoSchema;
use PHPUnit\Framework\TestCase;

// contrato sobre o SQL: sem banco, confere o que a migração manda executar
class EnteFederadoSchemaTest extends TestCase
{
    public function testTableAndSequenceHaveTheConectaentePrefix()
    {
        $this->assertSame('conectaente_ente_federado', EnteFederadoSchema::TABLE);
        $this->assertSame('conectaente_ente_federado_id_seq', EnteFederadoSchema::SEQUENCE);
        $this->assertStringContainsString(EnteFederadoSchema::SEQUENCE, EnteFederadoSchema::createSequence());
    }

    public function testColumnsMatchTheContract()
    {
        $sql = $this->normalizedTable();

        $this->assertStringContainsString('CREATE TABLE conectaente_ente_federado', $sql);
        $this->assertStringContainsString('id INT NOT NULL', $sql);
        $this->assertStringContainsString('name VARCHAR(255)', $sql);
        $this->assertStringContainsString('seal_id INT NOT NULL', $sql);
        $this->assertStringContainsString('token TEXT NOT NULL', $sql);
        $this->assertStringContainsString('cnpj VARCHAR(14)', $sql);
        $this->assertStringContainsString('create_timestamp TIMESTAMP', $sql);
        $this->assertStringContainsString('update_timestamp TIMESTAMP', $sql);
        $this->assertStringContainsString('PRIMARY KEY(id)', $sql);
    }

    public function testASealCannotServeTwoEntities()
    {
        $this->assertStringContainsString('CONSTRAINT conectaente_ente_federado_seal_id_uq UNIQUE (seal_id)', $this->normalizedTable());
    }

    public function testDeletingTheSealRemovesTheEntity()
    {
        $this->assertStringContainsString(
            'FOREIGN KEY (seal_id) REFERENCES seal (id) ON DELETE CASCADE',
            $this->normalizedTable()
        );
    }

    public function testMigrationHasPrefixedKeyAndGuards()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/db-updates.php');

        $this->assertStringContainsString("'create table conectaente_ente_federado'", $source);
        $this->assertStringContainsString('__sequence_exists(', $source);
        $this->assertStringContainsString('__table_exists(', $source);
    }

    private function normalizedTable()
    {
        return preg_replace('/\s+/', ' ', EnteFederadoSchema::createTable());
    }
}
