<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Database\FederativeEntitySchema;
use PHPUnit\Framework\TestCase;

// contrato sobre o SQL: sem banco, confere o que a migração manda executar
class FederativeEntitySchemaTest extends TestCase
{
    public function testTableAndSequenceHaveTheConectaentePrefix()
    {
        $this->assertSame('conectaente_federative_entity', FederativeEntitySchema::TABLE);
        $this->assertSame('conectaente_federative_entity_id_seq', FederativeEntitySchema::SEQUENCE);
        $this->assertStringContainsString(FederativeEntitySchema::SEQUENCE, FederativeEntitySchema::createSequence());
    }

    public function testColumnsMatchTheContract()
    {
        $sql = $this->normalizedTable();

        $this->assertStringContainsString('CREATE TABLE conectaente_federative_entity', $sql);
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
        $this->assertStringContainsString('CONSTRAINT conectaente_federative_entity_seal_id_uq UNIQUE (seal_id)', $this->normalizedTable());
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

        $this->assertStringContainsString("'conectaente: create federative_entity table'", $source);
        $this->assertStringContainsString('__sequence_exists(', $source);
        $this->assertStringContainsString('__table_exists(', $source);
    }

    public function testRenamesTheLegacyTableAndEverythingAttachedToIt()
    {
        $sql = implode("\n", FederativeEntitySchema::renameLegacyStatements());

        $this->assertStringContainsString('ALTER TABLE conectaente_ente_federado RENAME TO conectaente_federative_entity', $sql);
        $this->assertStringContainsString('ALTER SEQUENCE conectaente_ente_federado_id_seq RENAME TO conectaente_federative_entity_id_seq', $sql);
        $this->assertStringContainsString('RENAME CONSTRAINT conectaente_ente_federado_seal_id_uq TO conectaente_federative_entity_seal_id_uq', $sql);
        $this->assertStringContainsString('RENAME CONSTRAINT conectaente_ente_federado_seal_fk TO conectaente_federative_entity_seal_fk', $sql);
    }

    // tem de vir antes da criação, senão a instalação antiga ganharia uma segunda tabela vazia
    public function testLegacyRenameRunsBeforeTheCreation()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/db-updates.php');

        $this->assertLessThan(
            strpos($source, "'conectaente: create federative_entity table'"),
            strpos($source, "'conectaente: rename ente_federado table to federative_entity'")
        );
    }

    private function normalizedTable()
    {
        return preg_replace('/\s+/', ' ', FederativeEntitySchema::createTable());
    }
}
