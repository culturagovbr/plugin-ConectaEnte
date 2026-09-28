<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ParInformationService;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * Os quatro níveis do PAR na regra de publicação: presença sempre, consistência quando a árvore está à mão.
 */
class PublicationRequirementsParFieldsTest extends TestCase
{
    use PublicationRequirementsFixtures;

    function testSealedOpportunityWithoutAnySelectionIsMissingAllFourLevels()
    {
        $opportunity = $this->withoutParSelection($this->sealedOpportunity(Opportunity::STATUS_DRAFT));

        $missing = $this->missing($opportunity);

        $this->assertSame(
            ['O campo "Exercício do PAR" é obrigatório.'],
            $missing[CultBrMetadata::PAR_EXERCISE_ID],
            'Cada nível é nomeado, para a pendência ter rótulo próprio na lista.',
        );
        $this->assertArrayHasKey(CultBrMetadata::PAR_GOAL_ID, $missing);
        $this->assertArrayHasKey(CultBrMetadata::PAR_ACTION_ID, $missing);
        $this->assertArrayHasKey(CultBrMetadata::PAR_ACTIVITY_ID, $missing);
    }

    function testMissingActivityIsReportedAlone()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->{CultBrMetadata::PAR_ACTIVITY_ID} = '';
        $opportunity->save(true);

        $missing = $this->missing($opportunity);

        $this->assertArrayHasKey(CultBrMetadata::PAR_ACTIVITY_ID, $missing);
        $this->assertArrayNotHasKey(CultBrMetadata::PAR_EXERCISE_ID, $missing);
        $this->assertArrayNotHasKey(CultBrMetadata::PAR_GOAL_ID, $missing);
        $this->assertArrayNotHasKey(CultBrMetadata::PAR_ACTION_ID, $missing);
    }

    function testCompleteAndConsistentChainHasNoParPendency()
    {
        $missing = $this->missing($this->sealedOpportunity(Opportunity::STATUS_DRAFT));

        foreach (CultBrMetadata::PAR_KEYS as $key) {
            $this->assertArrayNotHasKey($key, $missing);
        }
    }

    function testInconsistentChainIsReportedOnTheActivity()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->{CultBrMetadata::PAR_ACTIVITY_ID} = '999';
        $opportunity->save(true);

        $missing = $this->missing($opportunity);

        $this->assertSame(
            ['A seleção do PAR não forma uma cadeia válida de exercício, meta, ação e atividade.'],
            $missing[CultBrMetadata::PAR_ACTIVITY_ID],
            'Atividade de outra ação não pode ir ao CultBR como se fosse da cadeia escolhida.',
        );
    }

    function testPresenceIsRequiredEvenWhenTheTreeIsUnavailable()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->{CultBrMetadata::PAR_ACTIVITY_ID} = '';
        $opportunity->save(true);
        $this->dropParCache($opportunity);

        $this->assertArrayHasKey(CultBrMetadata::PAR_ACTIVITY_ID, $this->missing($opportunity), 'A obrigatoriedade não depende do cache.');
    }

    function testConsistencyIsSkippedWhenTheTreeIsUnavailable()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->{CultBrMetadata::PAR_ACTIVITY_ID} = '999';
        $opportunity->save(true);
        $this->dropParCache($opportunity);

        $this->assertArrayNotHasKey(
            CultBrMetadata::PAR_ACTIVITY_ID,
            $this->missing($opportunity),
            'Sem a árvore à mão, a publicação não fica presa esperando a API; a cadeia é conferida no envio.',
        );
    }

    function testTheRuleNeverWaitsForTheApi()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $this->dropParCache($opportunity);
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, ['data' => []]);

        $this->missing($opportunity);

        $this->assertSame([], $transport->requestedUrls, 'Salvar o edital não pode ficar preso esperando o CultBR responder.');
    }

    private function withoutParSelection(Opportunity $opportunity): Opportunity
    {
        foreach (CultBrMetadata::PAR_KEYS as $key) {
            $opportunity->$key = '';
        }
        $opportunity->save(true);

        return $opportunity;
    }

    private function dropParCache(Opportunity $opportunity): void
    {
        $this->app->mscache->delete(ParInformationService::cacheKey($this->resolveFederativeEntity($opportunity)));
    }
}
