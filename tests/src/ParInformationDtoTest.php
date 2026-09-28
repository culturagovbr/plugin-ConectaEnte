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
        $tree = $this->treeOf([$this->entity([$this->exercise()])]);

        $exercise = $tree->exercises[0];
        $goal = $exercise->goals[0];
        $action = $goal->actions[0];

        $this->assertSame('2024', $exercise->year);
        $this->assertSame('Meta 1', $goal->name);
        $this->assertSame('Ação 1', $action->name);
        $this->assertSame('Atividade 1', $action->activities[0]->name);
    }

    function testOnlyTheIdIsRequiredAtEveryLevel()
    {
        $tree = $this->treeOf([$this->entity([
            ['id' => 1, 'metas' => [['id' => 2, 'acoes' => [['id' => 3, 'atividades' => [['id' => 4]]]]]]],
        ])]);

        $exercise = $tree->exercises[0];
        $goal = $exercise->goals[0];
        $action = $goal->actions[0];

        $this->assertNull($exercise->year);
        $this->assertNull($goal->name);
        $this->assertNull($goal->amount);
        $this->assertNull($action->activities[0]->name);
    }

    function testNumericIdsBecomeStrings()
    {
        $tree = $this->treeOf([$this->entity([
            ['id' => 10, 'metas' => [['id' => 20, 'acoes' => [['id' => 30, 'atividades' => [['id' => 40]]]]]]],
        ])]);

        $exercise = $tree->exercises[0];

        $this->assertSame('10', $exercise->id, 'O metadado gravado é string; comparar com int falharia ao reabrir a edição.');
        $this->assertSame('20', $exercise->goals[0]->id);
        $this->assertSame('30', $exercise->goals[0]->actions[0]->id);
        $this->assertSame('40', $exercise->goals[0]->actions[0]->activities[0]->id);
    }

    function testEachLevelSerializesOnlyTheFieldsItsSchemaHas()
    {
        $serialized = json_decode(json_encode($this->treeOf([$this->entity([$this->exercise()])])), true);

        $exercise = $serialized['exercicios'][0];
        $goal = $exercise['metas'][0];
        $action = $goal['acoes'][0];

        $this->assertSame(['id', 'ano', 'metas'], array_keys($exercise), 'ParExercicioSchema é { id, ano, metas }.');
        $this->assertSame(['id', 'nome', 'valor', 'acoes'], array_keys($goal));
        $this->assertSame(['id', 'nome', 'valor', 'atividades'], array_keys($action));
        $this->assertSame(['id', 'nome', 'valor'], array_keys($action['atividades'][0]));
    }

    function testEntityIsMatchedByDocumentIgnoringPunctuation()
    {
        $tree = $this->treeOf([
            $this->entity([['id' => 'outro']], '99999999000191'),
            $this->entity([$this->exercise()], '12.200.176/0001-76'),
        ]);

        $this->assertCount(1, $tree->exercises);
        $this->assertSame('2024', $tree->exercises[0]->year, 'A árvore é a do ente do token, não a do primeiro item.');
    }

    function testEntitiesOfAnotherDocumentAreLogged()
    {
        $handler = $this->captureLog();

        $this->treeOf([
            $this->entity([['id' => 'a']], '99999999000191'),
            $this->entity([['id' => 'b']], '11222333000181'),
            $this->entity([$this->exercise()]),
        ]);

        $this->assertTrue($handler->hasWarningThatContains('2 de 3 entes'), 'Ente descartado não pode sumir em silêncio.');
    }

    function testNoMatchingEntityIsLoggedAndGivesAnEmptyTree()
    {
        $handler = $this->captureLog();

        $tree = $this->treeOf([$this->entity([$this->exercise()], '99999999000191')]);

        $this->assertSame([], $tree->exercises);
        $this->assertTrue($handler->hasWarningThatContains('1 de 1 entes'));
    }

    function testRepeatedDocumentUsesTheFirstAndLogs()
    {
        $handler = $this->captureLog();

        $tree = $this->treeOf([
            $this->entity([['id' => 'primeiro']]),
            $this->entity([['id' => 'segundo']]),
        ]);

        $this->assertSame('primeiro', $tree->exercises[0]->id);
        $this->assertTrue($handler->hasWarningThatContains('2 entes casaram'));
    }

    function testBodyWithoutDataGivesAnEmptyTree()
    {
        $this->assertSame([], ParInformation::fromApiListResponse([], self::DOCUMENT)->exercises);
        $this->assertSame([], ParInformation::fromApiListResponse(['data' => []], self::DOCUMENT)->exercises);
        $this->assertSame([], ParInformation::fromApiListResponse(['data' => ['lixo']], self::DOCUMENT)->exercises);
    }

    function testBodyWhoseDataIsNotAListDoesNotBlowUp()
    {
        foreach (['texto', 7, null, ['cnpj' => self::DOCUMENT]] as $data) {
            $this->assertSame(
                [],
                ParInformation::fromApiListResponse(['data' => $data], self::DOCUMENT)->exercises,
                'JSON válido fora do contrato vira árvore vazia, não TypeError no job.',
            );
        }
    }

    function testConsistentPathIsAccepted()
    {
        $tree = $this->treeOf([$this->entity([$this->exercise()])]);

        $this->assertTrue($tree->isConsistentPath('2024', 'm1', 'a1', 'at1'));
    }

    function testPathIsRejectedWhenAnyLevelIsNotChildOfThePrevious()
    {
        $tree = $this->treeOf([$this->entity([$this->exercise()])]);

        $this->assertFalse($tree->isConsistentPath('outro', 'm1', 'a1', 'at1'), 'exercício inexistente');
        $this->assertFalse($tree->isConsistentPath('2024', 'outra', 'a1', 'at1'), 'meta de outro exercício');
        $this->assertFalse($tree->isConsistentPath('2024', 'm1', 'outra', 'at1'), 'ação de outra meta');
        $this->assertFalse($tree->isConsistentPath('2024', 'm1', 'a1', 'outra'), 'atividade de outra ação');
    }

    private function treeOf(array $data): ParInformation
    {
        return ParInformation::fromApiListResponse(['data' => $data], self::DOCUMENT);
    }

    private function entity(array $exercises, ?string $document = null): array
    {
        return ['cnpj' => $document ?? self::DOCUMENT, 'exercicios' => $exercises];
    }

    private function exercise(): array
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
