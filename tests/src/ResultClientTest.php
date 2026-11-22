<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Client;
use ConectaEnte\Http\Response;
use ConectaEnte\Http\Transport\FixtureTransport;
use ConectaEnte\Plugin;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Doubles\QueueTransport;

/**
 * As quatro rotas do resultado de edital, com os corpos que a Conecta Ente devolveu em homologação.
 */
class ResultClientTest extends TestCase
{
    const HOST = 'https://ente.conecta.hmg.cultbr.cultura.gov.br';

    const REQUIRED_MISSING = [
        'message' => 'Erro ao processar a requisição. Há questões obrigatórias não respondidas: Declarações com assinatura via certificado digital do govbr..',
        'errors' => [['field' => 'questao:13934', 'message' => 'Esta questão é obrigatória.', 'location' => ['body', 'respostas', '13934']]],
        'error_count' => 1,
    ];

    const INVALID_ROWS = [
        'message' => 'Há linhas inválidas. Nenhuma linha foi salva.',
        'total_linhas' => 15,
        'inconsistencias' => [[
            'linha' => 2,
            'regra' => 'cep_local_execucao_obrigatorio',
            'coluna' => 'cep_local_execucao',
            'indice' => 0,
            'categoria' => 'erro de obrigatoriedade',
            'descricao' => "O formato de execução 'Presencial Itinerante' exige o preenchimento do CEP do local de execução",
            'valor_recebido' => '',
        ]],
        'processamento_id' => null,
        'total_inconsistencias' => 1,
    ];

    const WITHOUT_SPREADSHEET_MODEL = ['detail' => 'Edital sem modelo de planilha de resultados: Forma de execução da ação 1.1 não respondida no edital'];

    function testTheSpreadsheetModelIsReadFromTheRouteOfTheCultBrEdital()
    {
        $transport = FakeTransport::replying(200, ['id_par_edital' => 53781, 'possui_planilha' => false, 'motivo' => 'Forma de execução da ação 1.1 não respondida no edital', 'campos' => []]);

        $read = (new Client(self::HOST, $transport))->getResultSpreadsheetModel('um-token', 53781);

        $this->assertSame(self::HOST . '/api/v1/oportunidades/53781/resultado/modelo-planilha', $transport->requestedUrls[0]);
        $this->assertSame(['token' => 'um-token'], $transport->sentHeaders[0]);
        $this->assertFalse($read->body['possui_planilha']);
    }

    function testAnUnknownEditalInTheCultBrFormatIsNotFound()
    {
        $read = (new Client(self::HOST, FakeTransport::replying(404, ['detail' => 'Edital não encontrado'])))->getResultSpreadsheetModel('um-token', 12459);

        $this->assertTrue($read->notFound);
        $this->assertSame('Edital não encontrado', $read->message);
    }

    function testANotFoundOutsideTheCultBrFormatIsUnreachable()
    {
        $read = (new Client(self::HOST, FakeTransport::replying(404, '<html>Not Found</html>')))->getResultSpreadsheetModel('um-token', 53781);

        $this->assertFalse($read->notFound);
        $this->assertTrue($read->unreachable);
    }

    function testARefusedTokenIsARejectionWithTheReason()
    {
        $read = (new Client(self::HOST, FakeTransport::replying(401, ['detail' => 'Token revogado'])))->getResultSpreadsheetModel('um-token', 53781);

        $this->assertFalse($read->unreachable);
        $this->assertSame('Token revogado', $read->message);
    }

    function testTheFormStructureIsAskedByModuleAndActionTypeWithoutTrailingSlash()
    {
        $transport = FakeTransport::replying(200, ['id_formulario' => 109, 'etapas' => []]);

        (new Client(self::HOST, $transport))->getResultFormStructure('um-token', 1);

        $this->assertSame(
            self::HOST . '/api/v1/par/formularios/estrutura?modulo=RESULTADO_EDITAL&id_tipo_acao=1',
            $transport->requestedUrls[0],
            'Com a barra final a API responde 307.',
        );
    }

    function testAnActionTypeWithoutFormIsNotFound()
    {
        $read = (new Client(self::HOST, FakeTransport::replying(404, ['detail' => 'Formulário não encontrado'])))->getResultFormStructure('um-token', 7);

        $this->assertTrue($read->notFound);
    }

    function testAPublishedResultIsKnownByItsId()
    {
        $transport = FakeTransport::replying(200, ['id_par_edital_resultado' => 20073, 'id_par_edital_resultado_tipo' => 3]);

        $published = (new Client(self::HOST, $transport))->getPublishedResult('um-token', 53781);

        $this->assertTrue($published->known);
        $this->assertTrue($published->exists());
        $this->assertSame(20073, $published->resultId);
        $this->assertSame(self::HOST . '/api/v1/oportunidades/53781/resultado/publicado', $transport->requestedUrls[0]);
    }

    function testOnlyTheCultBrSentenceMeansThereIsNoPublishedResult()
    {
        $published = (new Client(self::HOST, FakeTransport::replying(404, ['detail' => 'Resultado publicado não encontrado'])))->getPublishedResult('um-token', 53781);

        $this->assertTrue($published->known);
        $this->assertFalse($published->exists());
    }

    public static function inconclusivePublishedReads(): array
    {
        return [
            '404 de edital desconhecido' => [Response::received(404, '{"detail": "Edital não encontrado"}')],
            '404 de proxy' => [Response::received(404, '<html>Not Found</html>')],
            '500' => [Response::received(500, 'Internal Server Error')],
            '401' => [Response::received(401, '{"detail": "Token revogado"}')],
            '200 sem o id' => [Response::received(200, '{}')],
            'sem resposta' => [Response::failed('Operation timed out')],
        ];
    }

    #[DataProvider('inconclusivePublishedReads')]
    function testAnyOtherAnswerLeavesThePublishedResultUnknown(Response $response)
    {
        $published = (new Client(self::HOST, QueueTransport::replying($response)))->getPublishedResult('um-token', 53781);

        $this->assertFalse($published->known, 'Lido como "sem publicado", o envio sairia como um segundo final, que não se exclui.');
        $this->assertNotNull($published->reason);
    }

    function testAPublishedReadFromTheQueueUsesTheQueueTimeout()
    {
        $transport = FakeTransport::replying(404, ['detail' => 'Resultado publicado não encontrado']);

        (new Client(self::HOST, $transport))->getPublishedResult('um-token', 53781, queued: true);

        $this->assertSame([true], $transport->queuedReads, 'Dentro do job, os 10 s da tela esgotariam antes do CultBR responder.');
    }

    function testACreatedResultIsAccepted()
    {
        $transport = FakeTransport::replying(201, ['id_par_edital_resultado' => 20071, 'id_par_edital_resultado_tipo' => 2, 'processamento_id' => null]);

        $result = (new Client(self::HOST, $transport))->sendResult('um-token', 53781, ['id_tipo' => 2]);

        $this->assertTrue($result->accepted);
        $this->assertSame(20071, $result->resultId());
        $this->assertSame('POST', $transport->sentVerbs[0]);
        $this->assertSame(self::HOST . '/api/v1/oportunidades/53781/resultado', $transport->requestedUrls[0]);
    }

    function testACreatedResultWithoutTheIdIsStillAccepted()
    {
        $result = (new Client(self::HOST, FakeTransport::replying(201, '{}')))->sendResult('um-token', 53781, []);

        $this->assertTrue($result->accepted, 'Recusar faria o clique seguinte publicar de novo.');
        $this->assertNull($result->resultId());
    }

    function testATwoHundredIsNotTheAcceptanceOfTheResult()
    {
        $result = (new Client(self::HOST, FakeTransport::replying(200, ['id_par_edital_resultado' => 1])))->sendResult('um-token', 53781, []);

        $this->assertFalse($result->accepted, 'O contrato aceita o resultado com 201.');
    }

    function testAMissingRequiredQuestionComesAsAFieldError()
    {
        $result = (new Client(self::HOST, FakeTransport::replying(422, self::REQUIRED_MISSING)))->sendResult('um-token', 53781, []);

        $this->assertFalse($result->accepted);
        $this->assertSame(422, $result->status);
        $this->assertSame([['questionId' => 13934, 'field' => 'questao:13934', 'message' => 'Esta questão é obrigatória.']], $result->errors->fields);
        $this->assertStringContainsString('questões obrigatórias', $result->message);
    }

    function testInvalidRowsComeAsInconsistencies()
    {
        $result = (new Client(self::HOST, FakeTransport::replying(422, self::INVALID_ROWS)))->sendResult('um-token', 53781, []);

        $this->assertSame('cep_local_execucao', $result->errors->rows[0]['coluna']);
        $this->assertSame(0, $result->errors->rows[0]['indice']);
        $this->assertSame(1, $result->errors->totalRows);
        $this->assertSame('Há linhas inválidas. Nenhuma linha foi salva.', $result->message);
    }

    function testAnEditalWithoutSpreadsheetModelComesAsDetail()
    {
        $result = (new Client(self::HOST, FakeTransport::replying(422, self::WITHOUT_SPREADSHEET_MODEL)))->sendResult('um-token', 53781, []);

        $this->assertSame(self::WITHOUT_SPREADSHEET_MODEL['detail'], $result->message);
        $this->assertSame([], $result->errors->fields);
        $this->assertSame([], $result->errors->rows);
    }

    function testABadRequestIsARejection()
    {
        $result = (new Client(self::HOST, FakeTransport::replying(400, ['detail' => 'Edital não permite resultados.'])))->sendResult('um-token', 53781, []);

        $this->assertFalse($result->unreachable);
        $this->assertSame('Edital não permite resultados.', $result->message);
    }

    function testAServerErrorLeavesTheResultWithoutAnswer()
    {
        $result = (new Client(self::HOST, FakeTransport::replying(502, '<html>Bad Gateway</html>')))->sendResult('um-token', 53781, []);

        $this->assertTrue($result->unreachable, 'Sem resposta conclusiva: quem decide é a conciliação, nunca uma retentativa.');
        $this->assertFalse($result->accepted);
    }

    function testTheHistoryCanKeepADifferentBodyFromTheOneSent()
    {
        $client = new Client(self::HOST, FakeTransport::replying(201, ['id_par_edital_resultado' => 1]));

        $client->sendResult('um-token', 53781, ['linhas' => [['cpf' => '52998224725']]], ['linhas' => ['total' => 1, 'hash' => 'abc']]);

        $this->assertSame(['linhas' => ['total' => 1, 'hash' => 'abc']], $client->exchanges()[0]->requestPayload, 'As linhas trazem CPF de terceiros e não vão ao histórico.');
    }

    function testTheDevFixturesAnswerTheThreeReads()
    {
        $client = new Client(self::HOST, new FixtureTransport(Plugin::instance()->fixturesPath()), true);

        $this->assertFalse($client->getResultSpreadsheetModel('um-token', 53781)->body['possui_planilha'], 'A principal é a resposta real, sem planilha.');
        $this->assertSame(109, $client->getResultFormStructure('um-token', 1)->body['id_formulario']);
        $this->assertTrue($client->getPublishedResult('um-token', 53781)->exists());
    }

    function testTheAlternativeFixturesAreValidJson()
    {
        foreach (['modelo-planilha.com-planilha.json', 'estrutura.acao-9.json'] as $file) {
            $this->assertIsArray(json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/' . $file), true), $file);
        }
    }
}
