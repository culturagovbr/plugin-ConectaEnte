<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Dto\ParInformation;
use MapasCulturais\App;
use Monolog\Handler\TestHandler;
use Tests\Abstract\TestCase;

/**
 * A árvore do PAR lida do corpo da API: o que o contrato garante em cada nível e o que vira nulo.
 */
class ParInformationDtoTest extends TestCase
{
    const DOCUMENT = '12200176000176';

    function testReadsTheWholeTreeOfTheMatchingEntity()
    {
        $tree = $this->treeOf([$this->entity([$this->exercicio()])]);

        $exercicio = $tree->exercicios[0];
        $meta = $exercicio->metas[0];
        $acao = $meta->acoes[0];

        $this->assertSame('2024', $exercicio->ano);
        $this->assertSame('Meta 1', $meta->nome);
        $this->assertSame('Ação 1', $acao->nome);
        $this->assertSame('Atividade 1', $acao->atividades[0]->nome);
    }

    function testOnlyTheIdIsRequiredAtEveryLevel()
    {
        $tree = $this->treeOf([$this->entity([
            ['id' => 1, 'metas' => [['id' => 2, 'acoes' => [['id' => 3, 'atividades' => [['id' => 4]]]]]]],
        ])]);

        $exercicio = $tree->exercicios[0];
        $meta = $exercicio->metas[0];
        $acao = $meta->acoes[0];

        $this->assertNull($exercicio->ano);
        $this->assertNull($meta->nome);
        $this->assertNull($meta->valor);
        $this->assertNull($acao->atividades[0]->nome);
    }

    function testNumericIdsBecomeStrings()
    {
        $tree = $this->treeOf([$this->entity([
            ['id' => 10, 'metas' => [['id' => 20, 'acoes' => [['id' => 30, 'atividades' => [['id' => 40]]]]]]],
        ])]);

        $exercicio = $tree->exercicios[0];

        $this->assertSame('10', $exercicio->id, 'O metadado gravado é string; comparar com int falharia ao reabrir a edição.');
        $this->assertSame('20', $exercicio->metas[0]->id);
        $this->assertSame('30', $exercicio->metas[0]->acoes[0]->id);
        $this->assertSame('40', $exercicio->metas[0]->acoes[0]->atividades[0]->id);
    }

    function testEachLevelSerializesOnlyTheFieldsItsSchemaHas()
    {
        $serialized = json_decode(json_encode($this->treeOf([$this->entity([$this->exercicio()])])), true);

        $exercicio = $serialized['exercicios'][0];
        $meta = $exercicio['metas'][0];
        $acao = $meta['acoes'][0];

        $this->assertSame(['id', 'ano', 'metas'], array_keys($exercicio), 'ParExercicioSchema é { id, ano, metas }.');
        $this->assertSame(['id', 'nome', 'valor', 'acoes'], array_keys($meta));
        $this->assertSame(['id', 'nome', 'valor', 'atividades'], array_keys($acao));
        $this->assertSame(['id', 'nome', 'valor'], array_keys($acao['atividades'][0]));
    }

    function testEntityIsMatchedByDocumentIgnoringPunctuation()
    {
        $tree = $this->treeOf([
            $this->entity([['id' => 'outro']], '99999999000191'),
            $this->entity([$this->exercicio()], '12.200.176/0001-76'),
        ]);

        $this->assertCount(1, $tree->exercicios);
        $this->assertSame('2024', $tree->exercicios[0]->ano, 'A árvore é a do ente do token, não a do primeiro item.');
    }

    function testEntitiesOfAnotherDocumentAreLogged()
    {
        $handler = $this->captureLog();

        $this->treeOf([
            $this->entity([['id' => 'a']], '99999999000191'),
            $this->entity([['id' => 'b']], '11222333000181'),
            $this->entity([$this->exercicio()]),
        ]);

        $this->assertTrue($handler->hasWarningThatContains('2 de 3 entes'), 'Ente descartado não pode sumir em silêncio.');
    }

    function testNoMatchingEntityIsLoggedAndGivesAnEmptyTree()
    {
        $handler = $this->captureLog();

        $tree = $this->treeOf([$this->entity([$this->exercicio()], '99999999000191')]);

        $this->assertSame([], $tree->exercicios);
        $this->assertTrue($handler->hasWarningThatContains('1 de 1 entes'));
    }

    function testRepeatedDocumentUsesTheFirstAndLogs()
    {
        $handler = $this->captureLog();

        $tree = $this->treeOf([
            $this->entity([['id' => 'primeiro']]),
            $this->entity([['id' => 'segundo']]),
        ]);

        $this->assertSame('primeiro', $tree->exercicios[0]->id);
        $this->assertTrue($handler->hasWarningThatContains('2 entes casaram'));
    }

    function testBodyWithoutDataGivesAnEmptyTree()
    {
        $this->assertSame([], ParInformation::fromApiListResponse([], self::DOCUMENT)->exercicios);
        $this->assertSame([], ParInformation::fromApiListResponse(['data' => []], self::DOCUMENT)->exercicios);
        $this->assertSame([], ParInformation::fromApiListResponse(['data' => ['lixo']], self::DOCUMENT)->exercicios);
    }

    function testBodyWhoseDataIsNotAListDoesNotBlowUp()
    {
        foreach (['texto', 7, null, ['cnpj' => self::DOCUMENT]] as $data) {
            $this->assertSame(
                [],
                ParInformation::fromApiListResponse(['data' => $data], self::DOCUMENT)->exercicios,
                'JSON válido fora do contrato vira árvore vazia, não TypeError no job.',
            );
        }
    }

    function testConsistentPathIsAccepted()
    {
        $tree = $this->treeOf([$this->entity([$this->exercicio()])]);

        $this->assertTrue($tree->isConsistentPath('2024', 'm1', 'a1', 'at1'));
    }

    function testPathIsRejectedWhenAnyLevelIsNotChildOfThePrevious()
    {
        $tree = $this->treeOf([$this->entity([$this->exercicio()])]);

        $this->assertFalse($tree->isConsistentPath('outro', 'm1', 'a1', 'at1'), 'exercício inexistente');
        $this->assertFalse($tree->isConsistentPath('2024', 'outra', 'a1', 'at1'), 'meta de outro exercício');
        $this->assertFalse($tree->isConsistentPath('2024', 'm1', 'outra', 'at1'), 'ação de outra meta');
        $this->assertFalse($tree->isConsistentPath('2024', 'm1', 'a1', 'outra'), 'atividade de outra ação');
    }

    private function treeOf(array $data): ParInformation
    {
        return ParInformation::fromApiListResponse(['data' => $data], self::DOCUMENT);
    }

    private function entity(array $exercicios, ?string $document = null): array
    {
        return ['cnpj' => $document ?? self::DOCUMENT, 'exercicios' => $exercicios];
    }

    private function exercicio(): array
    {
        return [
            'id' => '2024',
            'ano' => '2024',
            'metas' => [[
                'id' => 'm1',
                'nome' => 'Meta 1',
                'valor' => '1000.00',
                'acoes' => [[
                    'id' => 'a1',
                    'nome' => 'Ação 1',
                    'valor' => '500.00',
                    'atividades' => [['id' => 'at1', 'nome' => 'Atividade 1', 'valor' => '250.00']],
                ]],
            ]],
        ];
    }

    private ?TestHandler $logHandler = null;

    private function captureLog(): TestHandler
    {
        App::i()->log->pushHandler($this->logHandler = new TestHandler());

        return $this->logHandler;
    }

    // o logger da App sobrevive ao teste: o handler precisa sair junto com ele
    protected function tearDown(): void
    {
        if ($this->logHandler) {
            App::i()->log->popHandler();
            $this->logHandler = null;
        }

        parent::tearDown();
    }
}
