<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Transport\JsonBody;
use PHPUnit\Framework\TestCase;

class JsonBodyTest extends TestCase
{
    public function testEncodesAPlainArray()
    {
        $this->assertSame('{"a":1}', JsonBody::encode(['a' => 1]));
    }

    public function testKeepsUnicodeUnescaped()
    {
        $this->assertSame('{"nome":"Edital de Música"}', JsonBody::encode(['nome' => 'Edital de Música']));
    }

    // bytes UTF-8 inválidos não podem virar corpo vazio silencioso: isso já mascarou um bug local como recusa do CultBR
    public function testInvalidUtf8RaisesALocalErrorNamingTheField()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/campo nome/');

        JsonBody::encode(['nome' => "Invalid \xB1\x31 UTF-8"]);
    }
}
