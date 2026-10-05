<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Transport\CurlTransport;
use ConectaEnte\Http\Transport\JsonBody;
use JsonException;
use Tests\Abstract\TestCase;

/**
 * O corpo JSON das requisições que escrevem no CultBR.
 */
class JsonBodyTest extends TestCase
{
    // sem a flag o json_encode devolve false, o corpo sai vazio e o CultBR recusa por schema
    function testInvalidUtf8ThrowsInsteadOfProducingAnEmptyBody()
    {
        $this->expectException(JsonException::class);

        JsonBody::encode(['detalhamento_objeto' => "\xB1\x31"]);
    }

    function testNanAndInfinityAlsoThrow()
    {
        $this->expectException(JsonException::class);

        JsonBody::encode(['valor_total_edital' => INF]);
    }

    // sem JSON_UNESCAPED_UNICODE o acento sairia como ã e o CultBR guardaria o escape
    function testAccentsGoAsThemselves()
    {
        $encoded = JsonBody::encode(['numero_e_titulo_edital' => 'Edital de Ação Cultural']);

        $this->assertStringContainsString('Ação', $encoded);
        $this->assertStringNotContainsString('\\u00e7', $encoded);
    }

    // o contrato distingue os quatro campos de array dos três de objeto: array vazio não pode virar {}
    function testEmptyArrayAndEmptyObjectKeepTheirShapes()
    {
        $encoded = JsonBody::encode(['categorias_edital' => [], 'recursos_outras_fontes' => (object) []]);

        $this->assertSame('{"categorias_edital":[],"recursos_outras_fontes":{}}', $encoded);
    }

    function testTheBodyKeepsTheOrderItWasGiven()
    {
        $encoded = JsonBody::encode(['id' => 7366, 'id_exercicio' => 17832, 'numero_e_titulo_edital' => 'Edital']);

        $this->assertSame('{"id":7366,"id_exercicio":17832,"numero_e_titulo_edital":"Edital"}', $encoded);
    }

    // o eixo dos tetos e o contexto, nao o verbo: o que importa e a relacao entre eles, nao o numero
    function testWritingIsAllowedToWaitLongerThanReading()
    {
        $this->assertGreaterThan(
            CurlTransport::INTERACTIVE_TIMEOUT,
            CurlTransport::QUEUED_TIMEOUT,
            'Teto de escrita igual ao de leitura esgotaria as tentativas contra um CultBR que só demorou.',
        );
        $this->assertGreaterThan(
            CurlTransport::INTERACTIVE_CONNECT_TIMEOUT,
            CurlTransport::QUEUED_CONNECT_TIMEOUT,
            'E o mesmo vale para o tempo de conectar.',
        );
    }

    // as barras saem escapadas, que e JSON valido: o que importa e a url sobreviver ao round-trip
    function testUrlsSurviveTheEncoding()
    {
        $url = 'https://cultura.gov.br/files/edital.pdf';

        $encoded = JsonBody::encode(['pdf_edital' => $url]);

        $this->assertSame($url, json_decode($encoded, true)['pdf_edital'], 'O escape é da serialização: o CultBR recebe a url original.');
    }
}
