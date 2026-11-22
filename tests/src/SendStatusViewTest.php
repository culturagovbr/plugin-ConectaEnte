<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ParInformationService;
use ConectaEnte\Services\SendOutcome;
use ConectaEnte\Services\SendStatusView;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * O que o indicador da oportunidade mostra enquanto não há desfecho gravado.
 */
class SendStatusViewTest extends TestCase
{
    use PublicationRequirementsFixtures;

    function testAnOpportunityWithoutTheFederativeSealHasNoIndicator()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertNull($this->pendingState($this->createOpportunity()));
    }

    function testThereIsNothingToShowWithoutAnOpportunity()
    {
        $this->assertNull($this->pendingState(null));
    }

    function testASealedOpportunityReadyToGoIsWaitingForTheSend()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);

        $this->assertSame(SendStatusView::AWAITING, $this->pendingState($opportunity));
    }

    // o gate barra, e a tela precisa dizer "ainda não enviada" em vez de "falhou"
    function testASealedOpportunityMissingARequiredFieldIsNotEligible()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);

        $this->assertSame(SendStatusView::INELIGIBLE, $this->pendingState($opportunity));
    }

    function testADraftIsNotEligibleBecauseDraftsAreNeverSent()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);

        $this->assertSame(SendStatusView::INELIGIBLE, $this->pendingState($opportunity));
    }

    // o desfecho é fato consumado: o gate de agora não apaga o envio de ontem
    function testARecordedOutcomeLeavesNothingPendingToExplain()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
        $opportunity->setMetadata(CultBrMetadata::SEND_STATUS, SendOutcome::ERROR);
        $opportunity->saveMetadata();

        $this->assertNull($this->pendingState($opportunity));
    }

    // o desfecho da oportunidade que perdeu o selo não volta à tela: o badge é da integração viva
    function testAnOpportunityWithoutTheSealShowsNoBadgeEvenWithARecordedOutcome()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $opportunity->setMetadata(CultBrMetadata::SEND_STATUS, SendOutcome::SUCCESS);
        $opportunity->saveMetadata();

        $this->assertFalse($this->view($opportunity)['sealed']);
    }

    function testAnOpportunityWhoseParActionLeftTheTreeIsSuspendedEvenWithARecordedOutcome()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $opportunity->setMetadata(CultBrMetadata::SEND_STATUS, SendOutcome::SUCCESS);
        $opportunity->saveMetadata();
        $this->primeParInformationCache($this->resolveFederativeEntity($opportunity), [['id' => '2024', 'ano' => '2024', 'metas' => []]]);

        $this->assertTrue($this->view($opportunity)['suspended'], 'O desfecho anterior não pode esconder que o próximo envio está barrado.');
    }

    function testAConsistentParChainIsNotSuspended()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);

        $this->assertFalse($this->view($opportunity)['suspended']);
    }

    function testAnIncompleteParSelectionIsIneligibleRatherThanSuspended()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $opportunity->{CultBrMetadata::PAR_ACTIVITY_ID} = '';
        $opportunity->save(true);

        $this->assertFalse($this->view($opportunity)['suspended'], 'Seleção por fazer é campo pendente, não ação que saiu do PAR.');
    }

    function testWithoutATreeInTheCacheNothingIsSuspended()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->app->mscache->delete(ParInformationService::cacheKey($this->resolveFederativeEntity($opportunity)));

        $this->assertFalse($this->view($opportunity)['suspended'], 'Sem árvore não há como afirmar que a ação saiu.');
    }

    function testAnUnsealedOpportunityIsNeverSuspended()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertFalse($this->view($this->createOpportunity())['suspended']);
    }

    private function view(?Opportunity $opportunity): array
    {
        return Plugin::instance()->sendStatusView()->of($opportunity);
    }

    private function pendingState(?Opportunity $opportunity): ?string
    {
        return $this->view($opportunity)['pendingSendState'];
    }
}
