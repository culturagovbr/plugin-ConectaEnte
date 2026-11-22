<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use ConectaEnte\Services\ResultForm;
use ConectaEnte\Services\ResultValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Abstract\TestCase;

/**
 * O validador lê o que a estrutura declara, nas estruturas reais das ações 1.1 e 3.1.
 */
class ResultValidatorTest extends TestCase
{
    const VALID_CPF = '529.982.247-25';

    public static function refusedAnswers(): array
    {
        return [
            'número que precisa ser inteiro' => [13918, 2.5],
            'número em texto' => [13918, 'dois'],
            'moeda negativa' => [13920, -1],
            'data inválida' => [13924, '15/12/2026'],
            'booleano em texto' => [13923, 'sim'],
            'opção fora da lista' => [13915, 'fe_inexistente'],
            'complemento ausente' => [13915, 'fe_outros'],
            'complemento vazio' => [13915, ['id' => 'fe_outros', 'complemento' => ' ']],
            'complemento acima do limite' => [13915, ['id' => 'fe_outros', 'complemento' => str_repeat('a', 121)]],
            'exclusiva junto de outra' => [13921, ['nenhum', 'heteroidentificacao']],
            'acima do limite de marcações' => [13934, ['declaracao_integral', 'declaracao_integral']],
            'texto acima do limite' => [13925, str_repeat('a', 701)],
            'tabela que não é lista de linhas' => [13933, ['nome' => 'x']],
            'CPF inválido na comissão' => [13933, [['nome' => 'Fulana', 'cpf' => '529.982.247-26', 'vinculo' => 'servidor público', 'categoria_grupo' => 'Todas']]],
            'vínculo fora das opções' => [13933, [['nome' => 'Fulana', 'cpf' => self::VALID_CPF, 'vinculo' => 'amigo', 'categoria_grupo' => 'Todas']]],
            'coluna obrigatória vazia' => [13933, [['nome' => '', 'cpf' => self::VALID_CPF, 'vinculo' => 'servidor público', 'categoria_grupo' => 'Todas']]],
        ];
    }

    #[DataProvider('refusedAnswers')]
    function testTheValidatorRefusesWhatTheStructureForbids(int $question, mixed $value)
    {
        $this->assertNotNull($this->error('estrutura.json', $question, $value));
    }

    public static function acceptedAnswers(): array
    {
        return [
            'inteiro' => [13918, 4],
            'moeda' => [13920, 15000.5],
            'data' => [13924, '2026-12-15'],
            'booleano' => [13923, false],
            'opção simples' => [13915, 'fe_tec'],
            'opção com complemento' => [13915, ['id' => 'fe_outros', 'complemento' => 'Termo de cessão de uso']],
            'lista com complemento' => [13921, ['heteroidentificacao', ['id' => 'outras_estrategias', 'complemento' => 'Banca documental']]],
            'declaração pelo id' => [13934, ['declaracao_integral']],
            'comissão válida' => [13933, [['nome' => 'Fulana', 'cpf' => self::VALID_CPF, 'vinculo' => 'servidor público', 'categoria_grupo' => 'Todas']]],
        ];
    }

    #[DataProvider('acceptedAnswers')]
    function testTheValidatorAcceptsWhatTheStructureAllows(int $question, mixed $value)
    {
        $this->assertNull($this->error('estrutura.json', $question, $value));
    }

    function testTheDynamicTableValidatesTheDocumentColumnByItsMask()
    {
        $row = ['tipo_documento' => 'CNPJ', 'numero_documento' => '12.ABC.345/01DE-35', 'nome_razao_social' => 'Empresa', 'valor_contrato' => 1000];

        $this->assertNull($this->error('estrutura.acao-9.json', 14784, [$row]), 'O CNPJ alfanumérico é válido.');
        $this->assertNotNull($this->error('estrutura.acao-9.json', 14784, [['numero_documento' => '11.111.111/1111-11'] + $row]));
        $this->assertNotNull($this->error('estrutura.acao-9.json', 14784, [['tipo_documento' => 'RG'] + $row]), 'O tipo vem da lista da coluna.');
        $this->assertNotNull($this->error('estrutura.acao-9.json', 14784, [['valor_contrato' => 'mil'] + $row]));
    }

    function testTheCheckboxOfAnotherActionIsValidatedByTheItemId()
    {
        $this->assertNull($this->error('estrutura.acao-9.json', 14793, ['declaracao_1', 'declaracao_2']));
        $this->assertNotNull($this->error('estrutura.acao-9.json', 14793, ['declaracao_3']));
    }

    function testTheEqRuleShowsTheCommentOnlyWhenTheExecutionDidNotGoAsPlanned()
    {
        $form = $this->form('estrutura.json');
        $comment = $form->question(13926);

        $this->assertFalse((new ResultValidator($form))->isVisible($comment, [13923 => true]));
        $this->assertTrue((new ResultValidator($form))->isVisible($comment, [13923 => false]));
    }

    function testTheIncludesRuleFollowsTheMarkedOptions()
    {
        $form = $this->form('estrutura.acao-9.json');
        $question = $form->question(14779);

        $this->assertTrue((new ResultValidator($form))->isVisible($question, [14778 => ['enq_i', 'enq_ii']]));
        $this->assertFalse((new ResultValidator($form))->isVisible($question, [14778 => ['enq_i']]));
    }

    function testAnInvisibleRequiredQuestionIsNotMissing()
    {
        $form = $this->form('estrutura.json');

        $missing = (new ResultValidator($form))->missing([13923 => true], []);

        $this->assertNotContains(13926, $missing, 'O servidor também não cobra a obrigatória invisível.');
        $this->assertContains(13926, (new ResultValidator($form))->missing([13923 => false], []));
    }

    function testAnUnknownTypeIsAVisibleError()
    {
        $error = (new ResultValidator($this->form('estrutura.json')))->error(['tipo_questao' => 'assinatura'], 'x');

        $this->assertStringContainsString('assinatura', $error);
    }

    function testTheDocumentCheckDigits()
    {
        $this->assertTrue(ResultValidator::isValidCpf('52998224725'));
        $this->assertFalse(ResultValidator::isValidCpf('11111111111'));
        $this->assertTrue(ResultValidator::isValidCnpj('11.222.333/0001-81'));
        $this->assertTrue(ResultValidator::isValidCnpj('12ABC34501DE35'));
        $this->assertFalse(ResultValidator::isValidCnpj('12ABC34501DE36'));
    }

    private function error(string $fixture, int $question, mixed $value): ?string
    {
        $form = $this->form($fixture);

        return (new ResultValidator($form))->error($form->question($question), $value);
    }

    private function form(string $fixture): ResultForm
    {
        return new ResultForm(json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/' . $fixture), true));
    }
}
