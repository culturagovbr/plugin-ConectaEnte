<?php

namespace Tests\ConectaEnte\Traits;

use ConectaEnte\Plugin;
use ConectaEnte\Services\PublicationRequirements;
use ConectaEnte\Vocabulary\CulturalStage;
use ConectaEnte\Vocabulary\ExecutionType;
use ConectaEnte\Vocabulary\Segment;
use ConectaEnte\Vocabulary\TargetingOption;
use ConectaEnte\Vocabulary\ThematicAgenda;
use DateTime;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\OpportunityFile;

trait PublicationRequirementsFixtures
{
    use ConectaEnteFixtures;

    protected function missing(Opportunity $opportunity): array
    {
        return (new PublicationRequirements(Plugin::instance()->publicationStamp()))->missing($opportunity);
    }

    /**
     * Oportunidade que cumpre toda a regra de publicação, relida do banco.
     */
    protected function completeOpportunity(int $status = Opportunity::STATUS_ENABLED): Opportunity
    {
        $opportunity = $this->opportunityWithoutRules($status);
        $this->attachRules($opportunity);

        return $this->reloaded($opportunity);
    }

    /**
     * Oportunidade completa, menos o regulamento, ainda sem reler.
     */
    protected function opportunityWithoutRules(int $status = Opportunity::STATUS_ENABLED): Opportunity
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity($status);
        $opportunity->vacancies = 10;
        $opportunity->totalResource = 1000;
        $opportunity->registrationProponentTypes = ['Pessoa Física', 'MEI', 'Coletivo'];
        $this->fillEditalFields($opportunity);

        if ($status === Opportunity::STATUS_ENABLED) {
            $opportunity->conectaente_publishedAt = new DateTime('2025-03-10 09:00:00');
        }

        $opportunity->save(true);

        return $opportunity;
    }

    private function fillEditalFields(Opportunity $opportunity): void
    {
        $opportunity->conectaente_executionType = ExecutionType::CULTURAL_EXECUTION->value;
        $opportunity->conectaente_segments = [Segment::COLLECTIONS->value];
        $opportunity->conectaente_culturalStages = [CulturalStage::ACCESS_MEDIATION_AND_ENJOYMENT->value];
        $opportunity->conectaente_thematicAgendas = [ThematicAgenda::FOOD_CULTURE->value];
        $opportunity->conectaente_priorityTerritories = [TargetingOption::NOT_TARGETED->value];
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
