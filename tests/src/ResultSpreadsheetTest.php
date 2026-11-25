<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\RegistrationFieldConfiguration;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RegistrationDirector;
use Tests\Traits\RegistrationFieldBuilder;
use Tests\Traits\RequestFactory;

/**
 * O modelo de planilha do edital e o de/para dos campos da ficha para as colunas que o CultBR declara.
 */
class ResultSpreadsheetTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RegistrationDirector;
    use RegistrationFieldBuilder;
    use RequestFactory;

    const WITHOUT_SPREADSHEET = ['id_par_edital' => 53781, 'possui_planilha' => false, 'motivo' => 'Forma de execução da ação 1.1 não respondida no edital', 'campos' => []];

    protected function tearDown(): void
    {
        Plugin::instance()->transport = null;

        parent::tearDown();
    }

    function testAnEditalWithoutSpreadsheetShowsTheReasonOfTheCultBr()
    {
        $opportunity = $this->editalWith(self::WITHOUT_SPREADSHEET);

        $view = $this->spreadsheet()->mappingView($opportunity);

        $this->assertFalse($view['hasSpreadsheet']);
        $this->assertSame('Forma de execução da ação 1.1 não respondida no edital', $view['reason']);
    }

    function testAnEditalWithSpreadsheetListsTheColumnsOfTheModel()
    {
        $view = $this->spreadsheet()->mappingView($this->editalWith($this->modelWithSpreadsheet()));

        $this->assertTrue($view['hasSpreadsheet']);
        $this->assertSame('tipo_agente', $view['columns'][0]['coluna']);
        $this->assertCount(10, $view['columns']);
    }

    function testAnEmptyMappingKeepsEveryProponentTypeWithBothDocuments()
    {
        $mapping = $this->spreadsheet()->mapping($this->editalWith(self::WITHOUT_SPREADSHEET));

        $this->assertSame(['Pessoa Física', 'MEI', 'Coletivo', 'Pessoa Jurídica'], array_keys($mapping['documents']));
        $this->assertSame(['cpf' => null, 'cnpj' => null], $mapping['documents']['MEI'], 'Um mapa vazio viraria lista no json e perderia o que o gestor escolher.');
    }

    function testTheSuggestionOnlyCountsAfterConfirmation()
    {
        $opportunity = $this->editalWith($this->modelWithSpreadsheet());
        $cpf = $this->field($opportunity, 'cpf', 'CPF do responsável');

        $view = $this->spreadsheet()->mappingView($this->reloaded($opportunity));
        $this->assertSame($cpf->id, ((array) $view['suggestion'])['cpf']);
        $this->assertArrayNotHasKey('cpf', $view['mapping']['columns'], 'A sugestão por nome já ligou colunas erradas no CultEditais.');

        $this->spreadsheet()->save($this->reloaded($opportunity), ['confirmSuggestion' => true]);

        $mapping = $this->spreadsheet()->mapping($this->reloaded($opportunity));
        $this->assertSame($cpf->id, $mapping['columns']['cpf']);
        $this->assertTrue($mapping['confirmed']);
    }

    function testAFieldOfTheWrongTypeIsRefused()
    {
        $opportunity = $this->editalWith($this->modelWithSpreadsheet());
        $date = $this->field($opportunity, 'date', 'Data de nascimento');

        $errors = $this->spreadsheet()->save($this->reloaded($opportunity), ['columns' => ['cpf' => $date->id]]);

        $this->assertArrayHasKey('columns.cpf', $errors);
    }

    function testAFieldOfAnotherEditalIsRefused()
    {
        $opportunity = $this->editalWith($this->modelWithSpreadsheet());
        $other = $this->field($this->sealedOpportunity(Opportunity::STATUS_ENABLED), 'cpf', 'CPF');

        $errors = $this->spreadsheet()->save($this->reloaded($opportunity), ['columns' => ['cpf' => $other->id]]);

        $this->assertArrayHasKey('columns.cpf', $errors);
    }

    function testAMultiValueFieldIsNotADocument()
    {
        $opportunity = $this->editalWith($this->modelWithSpreadsheet());
        $checkboxes = $this->field($opportunity, 'checkboxes', 'Documentos');

        $errors = $this->spreadsheet()->save($this->reloaded($opportunity), ['documents' => ['MEI' => ['cnpj' => $checkboxes->id]]]);

        $this->assertArrayHasKey('documents.MEI.cnpj', $errors);
    }

    function testTheDocumentOfTheProponentTypeOnlyTakesAFieldOfItsKind()
    {
        $opportunity = $this->editalWith($this->modelWithSpreadsheet());
        $select = $this->field($opportunity, 'select', 'Raça/etnia');
        $cpf = $this->field($opportunity, 'cpf', 'CPF');

        $errors = $this->spreadsheet()->save($this->reloaded($opportunity), ['documents' => ['MEI' => ['cpf' => $select->id, 'cnpj' => $cpf->id]]]);

        $this->assertSame(['documents.MEI.cpf', 'documents.MEI.cnpj'], array_keys($errors));
    }

    function testTheAgentFieldServesTheColumnOfTheDataItBrings()
    {
        $opportunity = $this->editalWith($this->modelWithSpreadsheet());
        $cpf = $this->agentField($opportunity, 'agent-owner-field', 'cpf');
        $race = $this->agentField($opportunity, 'agent-owner-field', 'raca');
        $cnpj = $this->agentField($opportunity, 'agent-collective-field', 'cnpj');
        $disability = $this->agentField($opportunity, 'agent-owner-field', 'pessoaDeficiente');
        $location = $this->agentField($opportunity, 'agent-owner-field', '@location');

        $errors = $this->spreadsheet()->save($this->reloaded($opportunity), [
            'columns' => ['cpf' => $cpf->id, 'cnpj' => $cnpj->id, 'raca_etnia' => $race->id, 'pessoa_com_deficiencia' => $disability->id, 'segmento_cultural' => $location->id, 'nome_contemplado' => $disability->id],
            'documents' => ['MEI' => ['cpf' => $cpf->id, 'cnpj' => $race->id]],
        ]);

        $this->assertSame(['columns.pessoa_com_deficiencia', 'columns.segmento_cultural', 'columns.nome_contemplado', 'documents.MEI.cnpj'], array_keys($errors), 'Lista de vários valores e endereço não cabem na célula.');
    }

    function testTheNameOfTheAgentIsText()
    {
        $opportunity = $this->editalWith($this->modelWithSpreadsheet());
        $name = $this->agentField($opportunity, 'agent-owner-field', 'name');
        $fullName = $this->agentField($opportunity, 'agent-owner-field', 'nomeCompleto');

        $this->assertSame([], $this->spreadsheet()->save($this->reloaded($opportunity), ['columns' => ['nome_contemplado' => $name->id]]));
        $this->assertSame([], $this->spreadsheet()->save($this->reloaded($opportunity), ['columns' => ['segmento_cultural' => $fullName->id]]));
    }

    function testMeiKeepsCpfAndCnpjApart()
    {
        $opportunity = $this->editalWith($this->modelWithSpreadsheet());
        $cpf = $this->field($opportunity, 'cpf', 'CPF do responsável');
        $cnpj = $this->field($opportunity, 'cnpj', 'CNPJ do MEI');

        $this->assertSame([], $this->spreadsheet()->save($this->reloaded($opportunity), ['documents' => ['MEI' => ['cpf' => $cpf->id, 'cnpj' => $cnpj->id]]]));

        $this->assertSame(['cpf' => $cpf->id, 'cnpj' => $cnpj->id], $this->spreadsheet()->mapping($this->reloaded($opportunity))['documents']['MEI']);
    }

    function testAnEquivalenceMustBeAnOptionOfTheCultBr()
    {
        $opportunity = $this->editalWith($this->modelWithSpreadsheet());

        $errors = $this->spreadsheet()->save($opportunity, ['equivalences' => ['raca_etnia' => ['Negra' => 'Preta', 'Morena' => 'Inexistente']]]);

        $this->assertArrayHasKey('equivalences.raca_etnia', $errors);
        $this->assertSame(['Negra' => 'Preta'], $this->spreadsheet()->mapping($this->reloaded($opportunity))['equivalences']['raca_etnia']);
    }

    function testAFichaValueWithoutOptionIsPendingUntilItGetsAnEquivalence()
    {
        $opportunity = $this->editalWith($this->modelWithSpreadsheet());
        $registration = $this->registrationDirector->createSentRegistrations($this->reloaded($opportunity), 1, proponent_type: 'Pessoa Física')[0];
        $race = $this->field($opportunity, 'select', 'Raça ou cor');
        $this->app->em->getConnection()->executeStatement(
            "INSERT INTO registration_meta (id, object_id, key, value) VALUES (nextval('registration_meta_id_seq'), ?, ?, ?)",
            [$registration->id, "field_{$race->id}", 'Negra'],
        );
        $this->spreadsheet()->save($this->reloaded($opportunity), ['columns' => ['raca_etnia' => $race->id]]);

        $this->assertSame(['Negra'], $this->pendingEquivalences($opportunity)['raca_etnia']);

        $this->spreadsheet()->save($this->reloaded($opportunity), ['equivalences' => ['raca_etnia' => ['Negra' => 'Preta']]]);

        $this->assertArrayNotHasKey('raca_etnia', $this->pendingEquivalences($opportunity));
    }

    function testTheMappingStaysPrivateAndOutOfTheCoreApi()
    {
        $this->assertTrue($this->app->getRegisteredMetadata(Opportunity::class)[CultBrMetadata::RESULT_MAPPING]->private);
        $this->assertContains(CultBrMetadata::RESULT_MAPPING, CultBrMetadata::RESULT_KEYS);
        $this->assertContains(CultBrMetadata::RESULT_MAPPING, CultBrMetadata::SEND_STATE_KEYS);
    }

    function testTheRouteRefusesWhoDoesNotControlTheEdital()
    {
        $opportunity = $this->editalWith(self::WITHOUT_SPREADSHEET);
        $this->login($this->userDirector->createUser());

        $this->assertSame(403, $this->send($this->requestFactory->GET('conectaente', 'resultMapping', [$opportunity->id], ajax: true)));
    }

    function testTheAgentTypeAsksForTheEquivalenceOfEachProponentType()
    {
        $opportunity = $this->editalWith($this->modelWithSpreadsheet());
        $opportunity->registrationProponentTypes = ['MEI', 'Coletivo'];
        $opportunity->save(true);

        $this->assertSame(['MEI', 'Coletivo'], $this->pendingEquivalences($opportunity)['tipo_agente']);

        $this->spreadsheet()->save($this->reloaded($opportunity), ['equivalences' => ['tipo_agente' => ['MEI' => 'Microempreendedor individual - MEI']]]);

        $this->assertSame(['Coletivo'], $this->pendingEquivalences($opportunity)['tipo_agente']);
    }

    private function pendingEquivalences(Opportunity $opportunity): array
    {
        return (array) $this->spreadsheet()->mappingView($this->reloaded($opportunity))['pendingEquivalences'];
    }

    private function editalWith(array $model): Opportunity
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $opportunity = $this->reloaded($opportunity);
        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_ID, (string) random_int(1, 9999999));
        $opportunity->saveMetadata();
        Plugin::instance()->transport = FakeTransport::replying(200, $model);

        return $this->reloaded($opportunity);
    }

    private function field(Opportunity $opportunity, string $type, string $title): RegistrationFieldConfiguration
    {
        return $this->registrationFieldBuilder
            ->reset($this->reloaded($opportunity), $type, uniqid('campo-'))
            ->fillRequiredProperties()
            ->setTitle($title)
            ->save()
            ->getInstance();
    }

    private function agentField(Opportunity $opportunity, string $type, string $property): RegistrationFieldConfiguration
    {
        $field = $this->field($opportunity, $type, $property);
        $field->config = ['entityField' => $property];
        $field->save(true);

        return $field;
    }

    private function modelWithSpreadsheet(): array
    {
        return json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/modelo-planilha.com-planilha.json'), true);
    }

    private function spreadsheet(): \ConectaEnte\Services\ResultSpreadsheet
    {
        return Plugin::instance()->resultSpreadsheet();
    }
}
