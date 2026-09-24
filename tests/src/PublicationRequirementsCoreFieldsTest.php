<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use ConectaEnte\Services\PublicationRequirements;
use ConectaEnte\Vocabulary\LegalEntityType;
use DateTime;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\OpportunityFile;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;

class PublicationRequirementsCoreFieldsTest extends TestCase
{
    use ConectaEnteFixtures;

    function testCompleteOpportunityHasNothingMissing()
    {
        $this->assertSame([], $this->missing($this->completeOpportunity()));
    }

    function testVacanciesMustBeAPositiveNumber()
    {
        $opportunity = $this->completeOpportunity();
        $expected = ['O campo "Total de vagas" é obrigatório e deve ser maior que zero.'];

        $opportunity->vacancies = 0;
        $this->assertSame($expected, $this->missing($opportunity)['vacancies'] ?? null);

        $opportunity->vacancies = null;
        $this->assertSame($expected, $this->missing($opportunity)['vacancies'] ?? null);
    }

    function testTotalResourceMustBeAPositiveNumber()
    {
        $opportunity = $this->completeOpportunity();
        $expected = ['O campo "Valor total" é obrigatório e deve ser maior que zero.'];

        $opportunity->totalResource = '0';
        $this->assertSame($expected, $this->missing($opportunity)['totalResource'] ?? null);

        $opportunity->totalResource = '1.000,50';
        $this->assertSame($expected, $this->missing($opportunity)['totalResource'] ?? null);

        $opportunity->totalResource = null;
        $this->assertSame($expected, $this->missing($opportunity)['totalResource'] ?? null);
    }

    function testProponentTypesAreRequired()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->registrationProponentTypes = [];

        $this->assertSame(['O campo "Tipos do proponente" é obrigatório.'], $this->missing($opportunity)['registrationProponentTypes'] ?? null);
    }

    function testProponentTypeWithoutCultBrCounterpartIsNamed()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->registrationProponentTypes = ['Pessoa Física', 'Estrangeiro'];

        $this->assertSame(
            ['O tipo de proponente "Estrangeiro" não tem correspondente no CultBR.'],
            $this->missing($opportunity)['registrationProponentTypes'] ?? null,
        );
    }

    function testLegalEntityRequiresItsType()
    {
        $opportunity = $this->completeOpportunity();
        $opportunity->registrationProponentTypes = ['Pessoa Física', 'Pessoa Jurídica'];

        $this->assertSame(['conectaente_legalEntityTypes' => ['O campo "Tipo de pessoa jurídica" é obrigatório.']], $this->missing($opportunity));

        $opportunity->conectaente_legalEntityTypes = [LegalEntityType::NON_PROFIT->value];

        $this->assertSame([], $this->missing($opportunity));
    }

    function testLegalEntityTypeWithoutCultBrCounterpartDoesNotCount()
    {
        $opportunity = $this->completeOpportunity();
        $opportunity->registrationProponentTypes = ['Pessoa Jurídica'];

        $opportunity->conectaente_legalEntityTypes = ['Cooperativa'];
        $this->assertSame([
            'O campo "Tipo de pessoa jurídica" é obrigatório.',
            'O tipo de pessoa jurídica "Cooperativa" não tem correspondente no CultBR.',
        ], $this->missing($opportunity)['conectaente_legalEntityTypes'] ?? null);

        $opportunity->conectaente_legalEntityTypes = ['Cooperativa', LegalEntityType::FOR_PROFIT->value];
        $this->assertSame(
            ['O tipo de pessoa jurídica "Cooperativa" não tem correspondente no CultBR.'],
            $this->missing($opportunity)['conectaente_legalEntityTypes'] ?? null,
        );
    }

    function testRulesAreRequired()
    {
        $opportunity = $this->reloaded($this->opportunityWithoutRules());

        $this->assertSame(['rules' => ['O campo "Adicionar regulamento" é obrigatório.']], $this->missing($opportunity));
    }

    function testRangesMustAddUpToTheTotals()
    {
        $opportunity = $this->completeOpportunity();
        $opportunity->totalResource = 0.3;

        $opportunity->registrationRanges = [
            ['label' => 'Faixa A', 'limit' => 4, 'value' => 0.1],
            ['label' => 'Faixa B', 'limit' => 5, 'value' => 0.21],
        ];

        $this->assertSame([
            'registrationRangesVacancies' => ['O total de vagas das categorias deve ser igual ao Total de vagas definido;'],
            'registrationRangesTotalResource' => ['O total em valores das categorias deve ser igual ao Valor total definido;'],
        ], $this->missing($opportunity));
    }

    function testRangesAddingUpToTheTotalsWithinACentPass()
    {
        $opportunity = $this->completeOpportunity();
        $opportunity->totalResource = 0.3;

        $opportunity->registrationRanges = [
            ['label' => 'Faixa A', 'limit' => 4, 'value' => 0.1],
            ['label' => 'Faixa B', 'limit' => 6, 'value' => 0.2],
        ];

        $this->assertSame([], $this->missing($opportunity));
    }

    function testRangeLimitsWrittenAsDecimalOrTextStillAddUp()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->registrationRanges = [
            ['label' => 'Faixa A', 'limit' => 4.0, 'value' => 400],
            ['label' => 'Faixa B', 'limit' => '6', 'value' => '600'],
        ];

        $this->assertSame([], $this->missing($opportunity));
    }

    function testRangesAreNotComparedWithoutTheTotals()
    {
        $opportunity = $this->completeOpportunity();
        $opportunity->registrationRanges = [['label' => 'Faixa A', 'limit' => 4, 'value' => 100]];

        $opportunity->vacancies = null;
        $opportunity->totalResource = null;

        $this->assertSame(['vacancies', 'totalResource'], array_keys($this->missing($opportunity)));
    }

    function testPublishedOpportunityWithoutDateIsMissingIt()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->conectaente_publishedAt = null;

        $this->assertSame(['conectaente_publishedAt' => ['O campo "Data de publicação do edital" é obrigatório.']], $this->missing($opportunity));
    }

    function testDraftBeingPublishedIsNotAskedForTheDate()
    {
        $opportunity = $this->completeOpportunity(Opportunity::STATUS_DRAFT);

        $opportunity->status = Opportunity::STATUS_ENABLED;

        $this->assertSame([], $this->missing($opportunity));
    }

    function testContinuousFlowRequiresAnEndDate()
    {
        $opportunity = $this->completeOpportunity();
        $opportunity->isContinuousFlow = true;

        $opportunity->hasEndDate = false;
        $opportunity->registrationTo = Opportunity::CONTINUOUS_FLOW_DATE;
        $this->assertSame(['registrationTo' => ['A data final das inscrições é obrigatória: use "Definir data final das inscrições".']], $this->missing($opportunity));

        $opportunity->hasEndDate = true;
        $opportunity->registrationTo = '2026-12-31 18:00';
        $this->assertSame([], $this->missing($opportunity));
    }

    function testMissingEndDateIsLeftToTheCore()
    {
        $opportunity = $this->completeOpportunity();
        $opportunity->isContinuousFlow = true;
        $opportunity->hasEndDate = false;

        $opportunity->registrationTo = null;

        $this->assertSame([], $this->missing($opportunity));
    }

    function testContinuousFlowPlaceholderIsNotADeadline()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->registrationTo = Opportunity::CONTINUOUS_FLOW_DATE;

        $this->assertSame(
            ['registrationTo' => ['A data final das inscrições não pode ser 01/01/2111, a data que o fluxo contínuo usa quando não há data final.']],
            $this->missing($opportunity),
        );
    }

    private function missing(Opportunity $opportunity): array
    {
        return (new PublicationRequirements(Plugin::instance()->publicationStamp()))->missing($opportunity);
    }

    private function completeOpportunity(int $status = Opportunity::STATUS_ENABLED): Opportunity
    {
        $opportunity = $this->opportunityWithoutRules($status);
        $this->attachRules($opportunity);

        return $this->reloaded($opportunity);
    }

    private function opportunityWithoutRules(int $status = Opportunity::STATUS_ENABLED): Opportunity
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity($status);
        $opportunity->vacancies = 10;
        $opportunity->totalResource = 1000;
        $opportunity->registrationProponentTypes = ['Pessoa Física', 'MEI', 'Coletivo'];

        if ($status === Opportunity::STATUS_ENABLED) {
            $opportunity->conectaente_publishedAt = new DateTime('2025-03-10 09:00:00');
        }

        $opportunity->save(true);

        return $opportunity;
    }

    private function attachRules(Opportunity $opportunity): void
    {
        $path = sys_get_temp_dir() . '/' . uniqid('regulamento-') . '.pdf';
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        $file = new OpportunityFile([
            'error' => UPLOAD_ERR_OK,
            'name' => 'regulamento.pdf',
            'type' => 'application/pdf',
            'tmp_name' => $path,
            'size' => filesize($path),
        ]);
        $file->owner = $opportunity;
        $file->group = 'rules';
        $file->save(true);

        @unlink($path);
    }
}
