<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Vocabulary\LegalQuota;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * O bloco no formato que o componente da aba grava: quatro linhas com rótulo do vocabulário,
 * as três cotas legais nas posições que a regra confere.
 */
class QuotaReservationBlockTest extends TestCase
{
    use PublicationRequirementsFixtures;

    function testBlockWrittenByTheTabSurvivesTheDatabase()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::QUOTA_RESERVATION} = $this->blockAsTheTabWritesIt(3, 1, 1, 5);
        $opportunity->save(true);

        $stored = $this->reloaded($opportunity)->{CultBrMetadata::QUOTA_RESERVATION};

        $this->assertCount(4, $stored);
        $this->assertSame(LegalQuota::BLACK_PEOPLE->value, $stored[0]->label);
        $this->assertSame(3, $stored[0]->vagas);
        $this->assertSame(300, $stored[0]->valorDestinado, 'Valor sem centavos volta do json como inteiro: quem monta o payload converte.');
        $this->assertFalse($stored[0]->naoAplicavel);
        $this->assertSame(LegalQuota::OPEN_COMPETITION->value, $stored[3]->label);
    }

    function testCentsSurviveTheDatabase()
    {
        $opportunity = $this->completeOpportunity();

        $block = $this->blockAsTheTabWritesIt(1, 1, 1, 1);
        $block[0]['valorDestinado'] = 301.55;
        $opportunity->{CultBrMetadata::QUOTA_RESERVATION} = $block;
        $opportunity->save(true);

        $stored = $this->reloaded($opportunity)->{CultBrMetadata::QUOTA_RESERVATION};

        $this->assertSame(301.55, $stored[0]->valorDestinado);
    }

    function testBlockWrittenByTheTabSatisfiesThePublicationRule()
    {
        $opportunity = $this->completeOpportunity();
        $opportunity->vacancies = 10;

        $opportunity->{CultBrMetadata::QUOTA_RESERVATION} = $this->blockAsTheTabWritesIt(3, 1, 1, 5);

        $this->assertSame([], $this->missing($opportunity));
    }

    function testNotApplicableLegalQuotasAreAcceptedWithTheirLabels()
    {
        $opportunity = $this->completeOpportunity();
        $opportunity->vacancies = 10;

        $block = $this->blockAsTheTabWritesIt(0, 0, 0, 10);
        foreach ([0, 1, 2] as $index) {
            $block[$index]['naoAplicavel'] = true;
        }
        $opportunity->{CultBrMetadata::QUOTA_RESERVATION} = $block;

        $this->assertSame([], $this->missing($opportunity));
    }

    function testLegalQuotaLabelsFollowTheOrderTheRuleChecks()
    {
        $labels = array_column($this->blockAsTheTabWritesIt(1, 1, 1, 1), 'label');

        $this->assertSame([
            'Pessoas negras (pretas e pardas)',
            'Pessoas indígenas',
            'Pessoas com deficiência',
            'Ampla concorrência',
        ], $labels, 'A regra identifica as cotas legais pela posição; o rótulo gravado não pode sair de ordem.');
    }

    function testLabelIsStoredInPortugueseEvenWhenTheScreenIsTranslated()
    {
        $opportunity = $this->completeOpportunity(Opportunity::STATUS_DRAFT);

        $opportunity->{CultBrMetadata::QUOTA_RESERVATION} = $this->blockAsTheTabWritesIt(1, 1, 1, 1);
        $opportunity->save(true);

        $stored = $this->reloaded($opportunity)->{CultBrMetadata::QUOTA_RESERVATION};

        foreach ($stored as $index => $quota) {
            $this->assertSame(
                LegalQuota::cases()[$index]->text(),
                $quota->label,
                'O rótulo vai ao payload em reserva_vagas_cotas: o valor gravado é o texto fixo, não o traduzido.',
            );
        }
    }

    /** @return array[] as quatro linhas como o componente da aba as grava */
    private function blockAsTheTabWritesIt(int $blackPeople, int $indigenousPeople, int $peopleWithDisabilities, int $openCompetition): array
    {
        $slots = [$blackPeople, $indigenousPeople, $peopleWithDisabilities, $openCompetition];

        return array_map(fn(LegalQuota $quota, int $count) => [
            'label' => $quota->text(),
            'vagas' => $count,
            'valorDestinado' => $count * 100,
            'naoAplicavel' => false,
        ], LegalQuota::cases(), $slots);
    }
}
