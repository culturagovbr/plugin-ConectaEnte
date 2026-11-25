<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Services\ResultForm;
use ConectaEnte\Services\ResultValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Abstract\TestCase;

/**
 * O papel das questões de anexo quando o edital leva, ou não, a planilha de contemplados.
 */
class ResultFormTest extends TestCase
{
    const SPREADSHEET_UPLOAD = 14170;
    const TEC_SPREADSHEET = 13930;
    const OPTIONAL_ATTACHMENT = 14167;
    const REQUIRED_ATTACHMENT = 13931;
    const TEXT = 14148;

    function testWithTheSpreadsheetItsQuestionIsNotDemanded()
    {
        $this->assertNotContains(self::SPREADSHEET_UPLOAD, $this->missing(hasSpreadsheet: true), 'Na 2.1 a API aceitou as linhas sem essa questão.');
        $this->assertContains(self::REQUIRED_ATTACHMENT, $this->missing(hasSpreadsheet: true), 'O anexo obrigatório que não é a planilha segue cobrado.');
    }

    function testWithoutTheSpreadsheetItsQuestionIsAFileLikeAnyOther()
    {
        $this->assertContains(self::SPREADSHEET_UPLOAD, $this->missing(hasSpreadsheet: false));
        $this->assertSame([], $this->form(hasSpreadsheet: false)->filledByLinesIds());
        $this->assertSame([], $this->form(hasSpreadsheet: false)->unavailableAttachmentIds());
    }

    function testTheTecSpreadsheetIsDispensedEvenWithoutTheModel()
    {
        $this->assertContains(self::TEC_SPREADSHEET, $this->form(hasSpreadsheet: false)->dispensedIds(), 'O contrato a dispensa sempre.');
    }

    function testTheLinesFillEverySpreadsheetQuestion()
    {
        $this->assertSame([self::SPREADSHEET_UPLOAD, self::TEC_SPREADSHEET], $this->form(hasSpreadsheet: true)->filledByLinesIds());
    }

    public static function attachments(): array
    {
        return [
            'a planilha por arquivo' => [self::SPREADSHEET_UPLOAD, true],
            'a planilha de TEC' => [self::TEC_SPREADSHEET, true],
            'o anexo opcional' => [self::OPTIONAL_ATTACHMENT, true],
            'o anexo obrigatório' => [self::REQUIRED_ATTACHMENT, false],
            'a questão que não é anexo' => [self::TEXT, false],
        ];
    }

    #[DataProvider('attachments')]
    function testWithTheSpreadsheetOnlyTheDemandedAttachmentStaysAvailable(int $questionId, bool $unavailable)
    {
        $form = $this->form(hasSpreadsheet: true);

        $this->assertSame($unavailable, $form->isAttachmentUnavailable($form->question($questionId)));
    }

    private function missing(bool $hasSpreadsheet): array
    {
        return (new ResultValidator($this->form($hasSpreadsheet)))->missing([], []);
    }

    private function form(bool $hasSpreadsheet): ResultForm
    {
        $question = fn(int $id, string $type, string $tag, bool $required) => ['id_questao_etapa' => $id, 'tipo_questao' => $type, 'tag' => $tag, 'requerida' => $required];

        return new ResultForm(['secoes' => [['etapas' => [['questoes' => [
            $question(self::SPREADSHEET_UPLOAD, 'anexo', 'upload_planilha_csv_1_5', true),
            $question(self::TEC_SPREADSHEET, 'anexo', 'planilha_de_resultados_conforme_padrao_de_dados', true),
            $question(self::OPTIONAL_ATTACHMENT, 'anexo', 'upload_diario_oficial_1_5', false),
            $question(self::REQUIRED_ATTACHMENT, 'anexo', 'resultado_1_1_planilha_bolsa', true),
            $question(self::TEXT, 'texto', 'espaco_nome_resultado_1_5', false),
        ]]]]]], $hasSpreadsheet);
    }
}
