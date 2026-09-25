<?php

namespace Tests\ConectaEnte\Traits;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Services\PublicationRequirements;
use ConectaEnte\Vocabulary\AffirmativeAction;
use ConectaEnte\Vocabulary\CulturalStage;
use ConectaEnte\Vocabulary\ExecutionType;
use ConectaEnte\Vocabulary\Segment;
use ConectaEnte\Vocabulary\TargetingOption;
use ConectaEnte\Vocabulary\ThematicAgenda;
use DateTime;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\OpportunityFile;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Entities\Term;
use MapasCulturais\Entities\User;
use Psr\Http\Message\ServerRequestInterface;

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

    /**
     * Oportunidade selada por um Ente Federado e completa também para o core; a incompleta fica sem o tipo de edital.
     */
    protected function sealedOpportunity(int $status, bool $isComplete = true): Opportunity
    {
        $opportunity = $this->coreCompleteOpportunity($status, $isComplete);
        $opportunity->createSealRelation($this->federativeSeal());

        return $opportunity;
    }

    /**
     * Selo de um Ente Federado ativo, com documento sorteado para não colidir com outro ente do teste.
     */
    protected function federativeSeal(): Seal
    {
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal, document: sprintf('%014d', random_int(0, 99999999999999)));

        return $seal;
    }

    // completa também para o core, que exige datas e área na publicação
    protected function coreCompleteOpportunity(int $status, bool $isComplete = true): Opportunity
    {
        $opportunity = $this->completeOpportunity($status);
        $this->login($this->app->repo(User::class)->find($this->app->user->id));

        $opportunity->registrationFrom = new DateTime('2026-10-01 00:00');
        $opportunity->registrationTo = new DateTime('2026-10-31 18:00');
        $opportunity->terms = ['area' => [$this->app->repo(Term::class)->findOneBy(['taxonomy' => 'area'])->term]];

        if (!$isComplete) {
            $opportunity->{CultBrMetadata::EXECUTION_TYPE} = null;
        }

        $opportunity->save(true);

        return $opportunity;
    }

    // a requisição parte do EntityManager vazio, como em produção
    protected function send(ServerRequestInterface $request): int
    {
        $this->app->em->clear();
        $this->login($this->app->repo(User::class)->find($this->app->user->id));
        $this->app->reset();
        $this->app->run($request, false);

        return $this->app->response->getStatusCode();
    }

    private function fillEditalFields(Opportunity $opportunity): void
    {
        $notApplicable = ['naoAplicavel' => true, 'vagas' => 0, 'valorDestinado' => 0];

        $opportunity->conectaente_executionType = ExecutionType::CULTURAL_EXECUTION->value;
        $opportunity->conectaente_segments = [Segment::COLLECTIONS->value];
        $opportunity->conectaente_culturalStages = [CulturalStage::ACCESS_MEDIATION_AND_ENJOYMENT->value];
        $opportunity->conectaente_thematicAgendas = [ThematicAgenda::FOOD_CULTURE->value];
        $opportunity->conectaente_priorityTerritories = [TargetingOption::NOT_TARGETED->value];
        $opportunity->conectaente_fundingSources = ['houveUtilizacao' => 'nao'];
        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'nao'];
        $opportunity->conectaente_affirmativeActions = ['opcoes' => [AffirmativeAction::NOT_PLANNED->value]];
        $opportunity->conectaente_quotaReservation = [$notApplicable, $notApplicable, $notApplicable, ['vagas' => 10, 'valorDestinado' => 1000]];
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
