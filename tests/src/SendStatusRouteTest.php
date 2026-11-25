<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Services\SendOutcome;
use ConectaEnte\Services\SendStatusView;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

/**
 * A rota que o selo do cabeçalho consulta para acompanhar o envio do edital.
 */
class SendStatusRouteTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    function testGuestIsAskedToLogIn()
    {
        $this->assertStatus401($this->requestFactory->GET('conectaente', 'sendStatus', [1]));
    }

    function testUserWhoCannotEditTheOpportunityIsRefused()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->login($this->userDirector->createUser());

        $this->assertSame(403, $this->send($this->requestFactory->GET('conectaente', 'sendStatus', [$opportunity->id])));
    }

    function testTheOutcomeArrivesWithoutReloadingThePage()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->assertSame(SendStatusView::AWAITING, $this->statusOf($opportunity)['pendingSendState']);

        $opportunity = $this->reloaded($opportunity);
        $opportunity->setMetadata(CultBrMetadata::SEND_STATUS, SendOutcome::SUCCESS);
        $opportunity->setMetadata(CultBrMetadata::SEND_AT, '2026-11-25 10:15:31');
        $opportunity->saveMetadata();

        $this->assertSame(['sealed' => true, 'suspended' => false, 'pendingSendState' => null, 'status' => SendOutcome::SUCCESS, 'at' => '2026-11-25 10:15:31'], $this->statusOf($opportunity));
    }

    private function statusOf(Opportunity $opportunity): array
    {
        $this->assertSame(200, $this->send($this->requestFactory->GET('conectaente', 'sendStatus', [$opportunity->id])));

        return json_decode((string) $this->app->response->getBody(), true);
    }
}
