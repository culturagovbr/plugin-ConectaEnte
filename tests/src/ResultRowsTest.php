<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\ResultCell;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ResultRows;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Registration;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RegistrationDirector;
use Tests\Traits\RegistrationFieldBuilder;
use Tests\Traits\RequestFactory;

/**
 * A grade de contemplados sobre o modelo `fomento_cultural_tec` montado pelo exemplo do contrato.
 */
class ResultRowsTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RegistrationDirector;
    use RegistrationFieldBuilder;
    use RequestFactory;

    const VALID_CPF = '52998224725';

    protected function tearDown(): void
    {
        Plugin::instance()->transport = null;

        parent::tearDown();
    }

    function testEveryAwardedRegistrationOfTheFinalPhaseIsARow()
    {
        $opportunity = $this->edital();
        $this->awarded($opportunity, 3);

        $page = $this->rows()->page($this->reloaded($opportunity));

        $this->assertSame(3, $page['total']);
        $this->assertCount(3, $page['rows']);
        $this->assertCount(10, $page['columns']);
    }

    function testTheAgentTypeAndTheValueComeFromTheRegistration()
    {
        $opportunity = $this->edital([['label' => 'Faixa A', 'limit' => 2, 'value' => 10000]]);
        $this->awarded($opportunity, 1, 'MEI', 'Faixa A');

        $cells = $this->rows()->page($this->reloaded($opportunity))['rows'][0]['cells'];

        $this->assertSame(['value' => 'MEI', 'source' => 'auto'], $cells['tipo_agente']);
        $this->assertSame(['value' => '5000.00', 'source' => 'auto'], $cells['valor_proposta'], 'Monetário com duas casas, como no exemplo do contrato.');
    }

    function testAListValueIsTranslatedByTheEquivalence()
    {
        $opportunity = $this->edital();
        $this->awarded($opportunity, 1, 'MEI');
        Plugin::instance()->resultSpreadsheet()->save($this->reloaded($opportunity), ['equivalences' => ['tipo_agente' => ['MEI' => 'Microempreendedor individual - MEI']]]);

        $cells = $this->rows()->page($this->reloaded($opportunity))['rows'][0]['cells'];

        $this->assertSame('Microempreendedor individual - MEI', $cells['tipo_agente']['value']);
    }

    function testTheManagerCellWinsOverTheFichaAndTheCalculated()
    {
        $opportunity = $this->edital();
        $registration = $this->awarded($opportunity, 1, 'MEI')[0];

        $this->assertNull($this->rows()->saveCell($this->reloaded($opportunity), $registration->id, 'tipo_agente', 'Pessoa física'));

        $this->assertSame(['value' => 'Pessoa física', 'source' => 'manager'], $this->rows()->page($this->reloaded($opportunity))['rows'][0]['cells']['tipo_agente']);
    }

    function testTheFichaValueComesFromTheMappedField()
    {
        $opportunity = $this->edital();
        $registration = $this->awarded($opportunity, 1)[0];
        $field = $this->field($this->reloaded($opportunity)->lastPhase, 'cpf', 'CPF');
        $this->fichaAnswer($registration, $field->id, self::VALID_CPF);
        Plugin::instance()->resultSpreadsheet()->save($this->reloaded($opportunity), ['documents' => ['Pessoa Física' => ['cpf' => $field->id]]]);

        $this->assertSame(['value' => self::VALID_CPF, 'source' => 'ficha'], $this->rows()->page($this->reloaded($opportunity))['rows'][0]['cells']['cpf']);
    }

    function testAnEmptyManagerCellIsADecisionAndNullGivesTheCellBack()
    {
        $opportunity = $this->edital();
        $registration = $this->awarded($opportunity, 1, 'MEI')[0];

        $this->rows()->saveCell($this->reloaded($opportunity), $registration->id, 'tipo_agente', '');
        $this->assertSame('manager', $this->rows()->page($this->reloaded($opportunity))['rows'][0]['cells']['tipo_agente']['source']);

        $this->rows()->saveCell($this->reloaded($opportunity), $registration->id, 'tipo_agente', null);
        $this->assertSame('auto', $this->rows()->page($this->reloaded($opportunity))['rows'][0]['cells']['tipo_agente']['source']);
    }

    function testTheCellRefusesOnlyWhatTheEditBreaks()
    {
        $opportunity = $this->edital();
        $registration = $this->awarded($opportunity, 1)[0];

        $this->assertNotNull($this->rows()->saveCell($this->reloaded($opportunity), $registration->id, 'cpf', '52998224726'));
        $this->assertNotNull($this->rows()->saveCell($this->reloaded($opportunity), $registration->id, 'raca_etnia', 'Morena'));
        $this->assertNotNull($this->rows()->saveCell($this->reloaded($opportunity), $registration->id, 'cpf', ['a', 'b']), 'Cada coluna aceita um único valor.');
        $this->assertNotNull($this->rows()->saveCell($this->reloaded($opportunity), $registration->id, 'inexistente', 'x'));
        $this->assertNull($this->rows()->saveCell($this->reloaded($opportunity), $registration->id, 'cnpj', '12.ABC.345/01DE-35'), 'O CNPJ alfanumérico é válido.');
    }

    function testARegistrationOfAnotherEditalIsRefused()
    {
        $opportunity = $this->edital();
        $other = $this->awarded($this->edital(), 1)[0];

        $this->assertNotNull($this->rows()->saveCell($this->reloaded($opportunity), $other->id, 'cpf', self::VALID_CPF));
    }

    function testARequiredEmptyColumnIsPendingAndAConditionalOneIsNot()
    {
        $opportunity = $this->edital();
        $this->awarded($opportunity, 1, 'MEI');

        $pending = $this->rows()->page($this->reloaded($opportunity))['rows'][0]['pending'];

        $this->assertContains('cpf', $pending);
        $this->assertNotContains('cnpj', $pending, 'A condição é texto livre: quem a cobra é o 422 de linhas.');
    }

    function testARegistrationWithoutProponentTypeIsPending()
    {
        $opportunity = $this->edital();
        $registration = $this->awarded($opportunity, 1)[0];
        $this->app->em->getConnection()->update('registration', ['proponent_type' => null], ['id' => $registration->id]);

        $this->assertContains(ResultRows::PROPONENT_TYPE_PENDING, $this->rows()->page($this->reloaded($opportunity))['rows'][0]['pending']);
    }

    function testThePageIsCutBySize()
    {
        $opportunity = $this->edital();
        $this->awarded($opportunity, 3);

        $page = $this->rows()->page($this->reloaded($opportunity), 2, 2);

        $this->assertCount(1, $page['rows']);
        $this->assertSame(3, $page['pendingRows'], 'As pendências contam todas as linhas, não só a página.');
    }

    function testRowsWithPendingAreNotReadyToSend()
    {
        $opportunity = $this->edital();
        $this->awarded($opportunity, 1);

        $this->assertNull($this->rows()->completeRows($this->reloaded($opportunity)));
    }

    function testAnIncompleteRowKeepsTheResultPending()
    {
        $opportunity = $this->edital();
        $this->awarded($opportunity, 1);
        $lastPhase = $this->reloaded($opportunity)->lastPhase;
        $lastPhase->publishedRegistrations = true;
        $lastPhase->save(true);
        $document = $this->resolveFederativeEntity($opportunity)->document;
        $opportunity = $this->reloaded($opportunity);
        $opportunity->setMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID, '1');
        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_FEDERATIVE_ENTITY_DOCUMENT, $document);
        $opportunity->saveMetadata();

        $state = Plugin::instance()->resultService()->stateOf($this->reloaded($opportunity));

        $this->assertSame(\ConectaEnte\Services\ResultState::PENDING, $state->state);
        $this->assertSame(1, $state->form['spreadsheetPending']);
    }

    function testTheCellEntityIsClosedToTheGenericPermission()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertFalse((new ResultCell)->canUser('view'));
        $this->assertFalse((new ResultCell)->canUser('remove'));
    }

    function testTheRouteRefusesWhoDoesNotControlTheEdital()
    {
        $opportunity = $this->edital();
        $this->login($this->userDirector->createUser());

        $this->assertSame(403, $this->send($this->requestFactory->GET('conectaente', 'resultRows', [$opportunity->id], ajax: true)));
    }

    private function edital(array $ranges = []): Opportunity
    {
        $opportunity = $this->reloaded($this->sealedOpportunity(Opportunity::STATUS_ENABLED));
        $opportunity->registrationRanges = $ranges;
        $opportunity->save(true);
        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_ID, (string) random_int(1, 9999999));
        $opportunity->saveMetadata();
        Plugin::instance()->transport = FakeTransport::replying(200, json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/modelo-planilha.com-planilha.json'), true));

        return $this->reloaded($opportunity);
    }

    /** @return Registration[] */
    private function awarded(Opportunity $opportunity, int $count, string $proponentType = 'Pessoa Física', ?string $range = null): array
    {
        $registrations = $this->registrationDirector->createSentRegistrations($this->reloaded($opportunity)->lastPhase, $count, proponent_type: $proponentType, range: $range);

        foreach ($registrations as $registration) {
            $registration->status = Registration::STATUS_APPROVED;
            $registration->save(true);
        }

        return $registrations;
    }

    private function field(Opportunity $phase, string $type, string $title): \MapasCulturais\Entities\RegistrationFieldConfiguration
    {
        return $this->registrationFieldBuilder->reset($phase, $type, uniqid('campo-'))->fillRequiredProperties()->setTitle($title)->save()->getInstance();
    }

    private function fichaAnswer(Registration $registration, int $fieldId, string $value): void
    {
        $this->app->em->getConnection()->executeStatement(
            "INSERT INTO registration_meta (id, object_id, key, value) VALUES (nextval('registration_meta_id_seq'), ?, ?, ?)",
            [$registration->id, "field_{$fieldId}", $value],
        );
    }

    private function rows(): ResultRows
    {
        return Plugin::instance()->resultRows();
    }
}
