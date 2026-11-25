<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\ApiErrors;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ResultForm;
use ConectaEnte\Services\ResultOutcome;
use ConectaEnte\Services\ResultSender;
use Tests\Abstract\TestCase;

/**
 * Os desfechos do envio de resultado em texto, a partir das respostas reais da Conecta Ente.
 */
class ResultOutcomeTest extends TestCase
{
    const REQUIRED_MISSING = ['message' => 'Erro ao processar a requisição. Há questões obrigatórias não respondidas: Declarações com assinatura via certificado digital do govbr..', 'errors' => [['field' => 'questao:13934', 'message' => 'Esta questão é obrigatória.', 'location' => ['body', 'respostas', '13934']]], 'error_count' => 1];
    const ATTACHMENT_MISSING = ['message' => 'Erro ao processar a requisição. Há questões obrigatórias não respondidas: Planilha de resultados — Bolsa.', 'errors' => [['field' => 'questao:13931', 'message' => 'Esta questão é obrigatória.', 'location' => ['body', 'respostas', '13931']]], 'error_count' => 1];
    const NO_SPREADSHEET_MODEL = ['detail' => 'Edital sem modelo de planilha de resultados: Forma de execução da ação 1.1 não respondida no edital'];

    function testARequiredQuestionIsNamedByTheStructureNotByTheServerPhrase()
    {
        $outcome = $this->describe(422, self::REQUIRED_MISSING);

        $this->assertCount(1, $outcome['items']);
        $this->assertStringStartsWith($this->labelOf(13934), $outcome['items'][0]);
        $this->assertStringNotContainsString('govbr..', $outcome['items'][0], 'A frase do servidor não é o texto da questão.');
    }

    function testAMissingAttachmentIsNamedToo()
    {
        $this->assertStringStartsWith($this->labelOf(13931), $this->describe(422, self::ATTACHMENT_MISSING)['items'][0]);
    }

    function testADetailIsShownAsText()
    {
        $outcome = $this->describe(422, self::NO_SPREADSHEET_MODEL);

        $this->assertSame(self::NO_SPREADSHEET_MODEL['detail'], $outcome['message']);
        $this->assertSame([], $outcome['items']);
    }

    function testARefusedRowShowsTheLineTheColumnNameAndTheRegistration()
    {
        $rows = [['linha' => 2, 'regra' => 'cep_local_execucao_obrigatorio', 'coluna' => 'cep_local_execucao', 'descricao' => 'O formato de execução exige o CEP do local de execução', 'registrationId' => 1731]];
        $outcome = (new ResultOutcome($this->form(), ['cep_local_execucao' => 'CEP do local de execução']))->describe($this->rejected(422, ['rows' => $rows, 'totalRows' => 15]));

        $this->assertStringContainsString('Linha 2', $outcome['items'][0]);
        $this->assertStringContainsString('1731', $outcome['items'][0]);
        $this->assertStringContainsString('CEP do local de execução:', $outcome['items'][0]);
        $this->assertSame(14, $outcome['hiddenRows']);
    }

    /** @dataProvider badRequests */
    function testEachCauseOfTheBadRequestHasItsMessage(string $detail, string $expected)
    {
        $this->assertStringContainsString($expected, $this->describe(400, ['detail' => $detail])['message']);
    }

    static function badRequests(): array
    {
        return [
            'tipo de ação sem resultado' => ['Edital não permite resultados.', 'não aceita resultado'],
            'questão fora da versão' => ['Questão 99999 não pertence à versão publicada do formulário.', 'formulário de resultado mudou'],
            'edital sem ação' => ['Edital sem ação associada.', 'sem ação associada'],
        ];
    }

    /** @dataProvider statusesWithTheirOwnMessage */
    function testAuthorizationAndMissingEditalHaveTheirOwnMessage(int $status, string $expected)
    {
        $this->assertStringContainsString($expected, $this->describe($status, ['detail' => 'Not authenticated'])['message']);
    }

    static function statusesWithTheirOwnMessage(): array
    {
        return [[401, 'token'], [403, 'permissão'], [404, 'não encontrou']];
    }

    function testASimulatedSendIsNotASuccess()
    {
        $simulated = (new ResultOutcome())->describe(['status' => ResultSender::STATUS_SIMULATED]);
        $success = (new ResultOutcome())->describe(['status' => ResultSender::STATUS_SUCCESS]);

        $this->assertNotSame($success['message'], $simulated['message']);
        $this->assertStringContainsString('nada foi enviado', $simulated['message']);
    }

    function testALocalRefusalShowsItsOwnReason()
    {
        $this->assertSame('Nada mudou desde o último resultado enviado.', (new ResultOutcome())->describe(['status' => ResultSender::STATUS_ERROR, 'reason' => 'Nada mudou desde o último resultado enviado.'])['message']);
    }

    function testAQuestionThatEndsInPunctuationTakesNoColon()
    {
        $errors = ['fields' => [['questionId' => 13924, 'field' => 'questao:13924', 'message' => 'A data é anterior à do edital.']]];

        $this->assertStringContainsString('? A data é anterior', (new ResultOutcome($this->form()))->describe($this->rejected(422, $errors))['items'][0]);
    }

    private function describe(int $status, array $body): array
    {
        $errors = ApiErrors::fromBody($body);

        return (new ResultOutcome($this->form()))->describe($this->rejected($status, ['fields' => $errors->fields, 'rows' => $errors->rows, 'totalRows' => $errors->totalRows, 'detail' => $errors->detail]));
    }

    private function rejected(int $status, array $errors): array
    {
        return ['status' => ResultSender::STATUS_REJECTED, 'reason' => null, 'errors' => ['status' => $status] + $errors];
    }

    private function labelOf(int $id): string
    {
        $question = $this->form()->question($id);

        return $question['config']['textoPergunta'] ?? $question['nome'];
    }

    private function form(): ResultForm
    {
        return new ResultForm(json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/estrutura.json'), true));
    }
}
