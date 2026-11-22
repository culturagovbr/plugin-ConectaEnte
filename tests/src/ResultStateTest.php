<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\Response;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ResultState;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Subsite;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Doubles\QueueTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

/**
 * A régua do resultado: o que é local vem antes do que exige o CultBR.
 */
class ResultStateTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    const STRUCTURE = ['id_formulario' => 109, 'id_formulario_versao' => 541, 'versao' => 12, 'secoes' => []];
    const STRUCTURE_CACHE_KEY = 'conectaente:result-form:1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->mscache->delete(self::STRUCTURE_CACHE_KEY);
    }

    protected function tearDown(): void
    {
        Plugin::instance()->transport = null;
        $this->app->setCurrentSubsiteId(null);
        $this->app->mscache->delete(self::STRUCTURE_CACHE_KEY);

        parent::tearDown();
    }

    function testAnUnsealedOpportunityHasNoTab()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertSame(ResultState::NO_TAB, $this->stateOf($this->createOpportunity())->state);
    }

    function testAPhaseHasNoTab()
    {
        $opportunity = $this->readyOpportunity();

        $this->assertSame(ResultState::NO_TAB, $this->stateOf($opportunity->lastPhase)->state, 'O resultado é do edital, e o edital é a raiz.');
    }

    function testADraftIsUnavailable()
    {
        $opportunity = $this->readyOpportunity(Opportunity::STATUS_DRAFT);

        $this->assertSame(ResultState::UNAVAILABLE, $this->stateOf($opportunity)->state);
    }

    function testTheStatusComesBeforeTheMissingParEditalId()
    {
        $opportunity = $this->readyOpportunity(Opportunity::STATUS_DRAFT);
        $this->store($opportunity, CultBrMetadata::PAR_EDITAL_ID, null);

        $this->assertSame(ResultState::UNAVAILABLE, $this->stateOf($opportunity)->state);
    }

    function testAnArchivedEditalStillHasItsResult()
    {
        $opportunity = $this->readyOpportunity();
        $opportunity->setStatus(Opportunity::STATUS_ARCHIVED);
        $opportunity->save(true);
        Plugin::instance()->transport = FakeTransport::replying(200, self::STRUCTURE);

        $this->assertSame(ResultState::COMPLETE, $this->stateOf($opportunity)->state, 'O edital encerrado ainda presta contas do resultado.');
    }

    function testWithoutTheParEditalIdThereIsNowhereToSend()
    {
        $opportunity = $this->readyOpportunity();
        $this->store($opportunity, CultBrMetadata::PAR_EDITAL_ID, null);

        $this->assertSame(ResultState::NO_PAR_EDITAL, $this->stateOf($opportunity)->state);
    }

    function testAnEditalSentByAnotherFederativeEntityIsBlocked()
    {
        $opportunity = $this->readyOpportunity();
        $this->store($opportunity, CultBrMetadata::PAR_EDITAL_FEDERATIVE_ENTITY_DOCUMENT, '99888777000166');

        $this->assertSame(ResultState::FEDERATIVE_ENTITY_CHANGED, $this->stateOf($opportunity)->state);
    }

    function testWithoutThePublishedFinalPhaseItWaits()
    {
        $opportunity = $this->readyOpportunity(finalPhasePublished: false);
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, self::STRUCTURE);

        $this->assertSame(ResultState::AWAITING_FINAL_PUBLICATION, $this->stateOf($opportunity)->state);
        $this->assertSame([], $transport->requestedUrls, 'O que é local se confere sem chamar o CultBR.');
    }

    function testAReadyEditalOpensTheFormWithItsVersion()
    {
        $opportunity = $this->readyOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(200, self::STRUCTURE);

        $state = $this->stateOf($opportunity)->jsonSerialize();

        $this->assertSame(ResultState::COMPLETE, $state['state']);
        $this->assertSame(['id' => 109, 'versionId' => 541, 'version' => 12], $state['form']);
    }

    function testAnActionTypeWithoutFormHasNoForm()
    {
        $opportunity = $this->readyOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(404, ['detail' => 'Formulário não encontrado']);

        $this->assertSame(ResultState::NO_FORM, $this->stateOf($opportunity)->state);
    }

    function testACultBrWithoutAnswerSaysSo()
    {
        $opportunity = $this->readyOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(503, 'Service Unavailable');

        $state = $this->stateOf($opportunity);

        $this->assertSame(ResultState::CULTBR_UNAVAILABLE, $state->state);
        $this->assertSame('unreachable', $state->reason);
    }

    function testARefusedTokenIsTheCultBrRefusing()
    {
        $opportunity = $this->readyOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(401, ['detail' => 'Token revogado']);

        $this->assertSame('rejected', $this->stateOf($opportunity)->reason);
    }

    function testAMissingActionTypeIsFetchedFromTheSpreadsheetModelAndKept()
    {
        $opportunity = $this->readyOpportunity();
        $this->store($opportunity, CultBrMetadata::PAR_ACTION_TYPE_ID, null);
        Plugin::instance()->transport = QueueTransport::replying(
            Response::received(200, json_encode(['id_par_edital' => 53781, 'acao' => ['id_par_tipo_acao' => 1], 'possui_planilha' => false])),
            Response::received(200, json_encode(self::STRUCTURE)),
        );

        $this->assertSame(ResultState::COMPLETE, $this->stateOf($opportunity)->state);
        $this->assertSame('1', $this->reloaded($opportunity)->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID), 'O tipo só vinha no eco do envio do edital; o acervo sem ele o ganha aqui.');
    }

    function testWithoutTheActionTypeTheCultBrIsUnavailable()
    {
        $opportunity = $this->readyOpportunity();
        $this->store($opportunity, CultBrMetadata::PAR_ACTION_TYPE_ID, null);
        Plugin::instance()->transport = FakeTransport::replying(503, 'Service Unavailable');

        $this->assertSame(ResultState::CULTBR_UNAVAILABLE, $this->stateOf($opportunity)->state);
    }

    function testTheStructureIsServedFromTheShortCache()
    {
        $opportunity = $this->readyOpportunity();
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, self::STRUCTURE);

        $this->stateOf($opportunity);
        $afterFirst = count($transport->requestedUrls);
        $this->stateOf($opportunity);

        $this->assertSame($afterFirst, count($transport->requestedUrls), 'A estrutura e o modelo de planilha vêm do cache na segunda leitura.');
    }

    function testTheRouteAnswersTheStateToWhoControlsTheEdital()
    {
        $opportunity = $this->readyOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(200, self::STRUCTURE);

        $this->assertSame(200, $this->send($this->resultRoute($opportunity->id)));
        $this->assertSame(ResultState::COMPLETE, json_decode((string) $this->app->response->getBody(), true)['state']);
    }

    function testTheRouteRefusesWhoDoesNotControlTheEdital()
    {
        $opportunity = $this->readyOpportunity();
        $this->login($this->userDirector->createUser());

        $this->assertSame(403, $this->send($this->resultRoute($opportunity->id)));
    }

    function testTheRouteAsksTheGuestToLogIn()
    {
        $this->assertStatus401($this->requestFactory->GET('conectaente', 'result', [1]));
    }

    function testTheRouteDoesNotAnswerFromAnotherSubsite()
    {
        $this->login($this->userDirector->createUser(['saasSuperAdmin']));
        $this->app->setCurrentSubsiteId($this->createSubsite('edital.exemplo')->id);
        $opportunity = $this->createOpportunity();

        $this->login($this->userDirector->createUser(['saasSuperAdmin']));
        $this->app->setCurrentSubsiteId($this->createSubsite('outra.exemplo')->id);

        $this->assertSame(404, $this->send($this->resultRoute($opportunity->id)));
    }

    private function readyOpportunity(int $status = Opportunity::STATUS_ENABLED, bool $finalPhasePublished = true): Opportunity
    {
        $opportunity = $this->sealedOpportunity($status);
        $document = $this->resolveFederativeEntity($opportunity)->document;
        $opportunity = $this->reloaded($opportunity);
        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_ID, (string) random_int(1, 9999999));
        $opportunity->setMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID, '1');
        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_FEDERATIVE_ENTITY_DOCUMENT, $document);
        $opportunity->saveMetadata();

        $lastPhase = $opportunity->lastPhase;
        $lastPhase->publishedRegistrations = $finalPhasePublished;
        $lastPhase->save(true);

        return $this->reloaded($opportunity);
    }

    private function store(Opportunity $opportunity, string $key, ?string $value): void
    {
        $opportunity->setMetadata($key, $value);
        $opportunity->saveMetadata();
    }

    private function stateOf(Opportunity $opportunity): ResultState
    {
        return Plugin::instance()->resultService()->stateOf($this->reloaded($opportunity));
    }

    private function resultRoute(int $opportunityId): ServerRequestInterface
    {
        return $this->requestFactory->GET('conectaente', 'result', [$opportunityId], ajax: true);
    }

    private function createSubsite(string $url): Subsite
    {
        $subsite = new Subsite;
        $subsite->name = "Instalação {$url}";
        $subsite->url = $url;
        $subsite->status = Subsite::STATUS_ENABLED;
        $subsite->namespace = 'Subsite';
        $subsite->save(true);

        return $subsite;
    }
}
