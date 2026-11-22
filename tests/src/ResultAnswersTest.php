<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ResultForm;
use ConectaEnte\Services\ResultState;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Registration;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\ConectaEnte\Traits\SendQueue;
use Tests\Traits\RegistrationDirector;
use Tests\Traits\RequestFactory;

/**
 * As respostas do formulário de resultado, sobre a estrutura 541 da ação 1.1.
 */
class ResultAnswersTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RegistrationDirector;
    use RequestFactory;
    use SendQueue;

    const ENROLLED = 13918;
    const AWARDED = 13919;
    const TRANSFERRED = 13920;
    const CATEGORIES = 13922;
    const EXECUTION_FORM = 13915;
    const DECLARATIONS = 13934;

    private ?ResultForm $form = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->mscache->delete('conectaente:result-form:1');
    }

    protected function tearDown(): void
    {
        Plugin::instance()->transport = null;
        $this->app->mscache->delete('conectaente:result-form:1');

        parent::tearDown();
    }

    function testEnrolledAndAwardedAreCountedAndReadOnly()
    {
        $opportunity = $this->readyOpportunity();
        $this->registrationDirector->createSentRegistrations($opportunity, 3, proponent_type: 'Pessoa Física');
        $this->awardedInFinalPhase($opportunity, 2);

        $computed = $this->answers()->computed($this->reloaded($opportunity), $this->form());

        $this->assertSame(3, $computed[self::ENROLLED]);
        $this->assertSame(2, $computed[self::AWARDED]);
    }

    function testTheTransferredValueIsASuggestionByTheValueOfEachRange()
    {
        $opportunity = $this->readyOpportunity(ranges: [['label' => 'Faixa A', 'limit' => 2, 'value' => 10000], ['label' => 'Faixa B', 'limit' => 2, 'value' => 20000]]);
        $this->awardedInFinalPhase($opportunity, 1, 'Faixa A');
        $this->awardedInFinalPhase($opportunity, 2, 'Faixa B');

        $suggested = $this->answers()->suggested($this->reloaded($opportunity), $this->form());

        $this->assertSame(25000.0, $suggested[self::TRANSFERRED], 'O valor da faixa é o total dela: por vaga, 5 mil e 10 mil.');
    }

    function testTheCategoryTableHasOneRowPerRange()
    {
        $opportunity = $this->readyOpportunity(ranges: [['label' => 'Faixa A', 'limit' => 2, 'value' => 10000], ['label' => 'Faixa B', 'limit' => 2, 'value' => 20000]]);
        $this->awardedInFinalPhase($opportunity, 1, 'Faixa A');

        $rows = $this->answers()->computed($this->reloaded($opportunity), $this->form())[self::CATEGORIES];

        $this->assertSame([
            ['categoria' => 'Faixa A', 'quantidade_contemplados' => 1, 'valor_individual' => 5000.0],
            ['categoria' => 'Faixa B', 'quantidade_contemplados' => 0, 'valor_individual' => 10000.0],
        ], $rows);
    }

    function testWithoutRangesTheCategoryTableIsOneRowFromTheTransferredAnswer()
    {
        $opportunity = $this->readyOpportunity();
        $this->awardedInFinalPhase($opportunity, 2);
        $this->answers()->save($this->reloaded($opportunity), $this->form(), [self::TRANSFERRED => 15000]);

        $rows = $this->answers()->computed($this->reloaded($opportunity), $this->form())[self::CATEGORIES];

        $this->assertSame([['categoria' => $opportunity->name, 'quantidade_contemplados' => 2, 'valor_individual' => 7500.0]], $rows);
    }

    function testWithoutAwardedTheSingleRowStaysOut()
    {
        $opportunity = $this->readyOpportunity();
        $this->answers()->save($this->reloaded($opportunity), $this->form(), [self::TRANSFERRED => 15000]);

        $this->assertArrayNotHasKey(self::CATEGORIES, $this->answers()->computed($this->reloaded($opportunity), $this->form()), 'A 13922 é opcional: sem contemplados, fica fora do corpo.');
    }

    function testTheValidAnswersAreSavedAndTheRefusedOnesComeBackWithTheReason()
    {
        $opportunity = $this->readyOpportunity();

        $errors = $this->answers()->save($this->reloaded($opportunity), $this->form(), [
            self::TRANSFERRED => 15000,
            self::ENROLLED => 99,
            999999 => 'questão de outra versão',
        ]);

        $this->assertSame([self::ENROLLED, 999999], array_keys($errors));
        $saved = $this->answers()->saved($this->reloaded($opportunity));
        $this->assertSame([self::TRANSFERRED => 15000], $saved['answers'], 'A recusada não derruba a válida.');
        $this->assertSame(541, $saved['versionId']);
    }

    function testAnswersFromAnotherVersionAreStale()
    {
        $opportunity = $this->readyOpportunity();
        $opportunity->{CultBrMetadata::RESULT_FORM} = ['versionId' => 296, 'answers' => [1844 => '2026-10-01']];
        $opportunity->saveMetadata();

        $this->assertTrue($this->answers()->isStale($this->reloaded($opportunity), $this->form()));
    }

    function testTheAnswersStayPrivate()
    {
        $this->assertTrue($this->app->getRegisteredMetadata(Opportunity::class)[CultBrMetadata::RESULT_FORM]->private, 'A comissão avaliadora vai com CPF.');
    }

    function testSavingTheAnswersDoesNotResendTheEdital()
    {
        $opportunity = $this->readyOpportunity();
        Plugin::instance()->mode = Plugin::MODE_LIVE;
        $this->purgeSendJobs();

        $this->answers()->save($this->reloaded($opportunity), $this->form(), [self::TRANSFERRED => 15000]);

        $this->assertSame([], $this->enqueuedSendJobs(), 'Salvar a oportunidade selada a reenviaria ao CultBR.');
    }

    function testTheStateIsPendingUntilEveryRequiredAnswerArrives()
    {
        $opportunity = $this->readyOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(200, $this->structure());

        $pending = Plugin::instance()->resultService()->stateOf($this->reloaded($opportunity));
        Plugin::instance()->resultService()->saveAnswers($this->reloaded($opportunity), $this->everyRequiredAnswer());
        $complete = Plugin::instance()->resultService()->stateOf($this->reloaded($opportunity));

        $this->assertSame(ResultState::PENDING, $pending->state);
        $this->assertContains(self::DECLARATIONS, $pending->form['missing']);
        $this->assertSame(ResultState::COMPLETE, $complete->state, json_encode($complete->form['missing']));
    }

    function testTheRouteSavesTheWholeFormForWhoControlsTheEdital()
    {
        $opportunity = $this->readyOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(200, $this->structure());

        $this->assertSame(200, $this->send($this->requestFactory->POST('conectaente', 'resultAnswers', [$opportunity->id], ['answers' => [self::TRANSFERRED => 15000, self::ENROLLED => 1]], ajax: true)));

        $body = json_decode((string) $this->app->response->getBody(), true);
        $this->assertArrayHasKey((string) self::ENROLLED, $body['errors']);
        $this->assertSame(15000, $body['result']['answers'][(string) self::TRANSFERRED]);
    }

    function testTheRouteRefusesWhoDoesNotControlTheEdital()
    {
        $opportunity = $this->readyOpportunity();
        $this->login($this->userDirector->createUser());

        $this->assertSame(403, $this->send($this->requestFactory->POST('conectaente', 'resultAnswers', [$opportunity->id], ['answers' => []], ajax: true)));
    }

    private function everyRequiredAnswer(): array
    {
        $answers = [];

        foreach ($this->form()->questions() as $id => $question) {
            $answers[$id] = match ($question['tipo_questao']) {
                'número', 'moeda' => 1,
                'booleano' => false,
                'data' => '2026-12-15',
                'múltipla escolha', 'tabela' => ['x'],
                default => 'x',
            };
        }

        return array_diff_key($answers, array_flip([self::ENROLLED, self::AWARDED, self::CATEGORIES, 13916, 13917]));
    }

    private function readyOpportunity(array $ranges = []): Opportunity
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $document = $this->resolveFederativeEntity($opportunity)->document;
        $opportunity = $this->reloaded($opportunity);
        $opportunity->registrationRanges = $ranges;
        $opportunity->save(true);
        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_ID, (string) random_int(1, 9999999));
        $opportunity->setMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID, '1');
        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_FEDERATIVE_ENTITY_DOCUMENT, $document);
        $opportunity->saveMetadata();

        $lastPhase = $opportunity->lastPhase;
        $lastPhase->publishedRegistrations = true;
        $lastPhase->save(true);

        return $this->reloaded($opportunity);
    }

    private function awardedInFinalPhase(Opportunity $opportunity, int $count, ?string $range = null): void
    {
        $finalPhase = $this->reloaded($opportunity)->lastPhase;

        foreach ($this->registrationDirector->createSentRegistrations($finalPhase, $count, proponent_type: 'Pessoa Física', range: $range) as $registration) {
            $registration->status = Registration::STATUS_APPROVED;
            $registration->save(true);
        }
    }

    private function structure(): array
    {
        return json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/estrutura.json'), true);
    }

    private function form(): ResultForm
    {
        return $this->form ??= new ResultForm($this->structure());
    }

    private function answers(): \ConectaEnte\Services\ResultAnswers
    {
        return Plugin::instance()->resultAnswers();
    }
}
