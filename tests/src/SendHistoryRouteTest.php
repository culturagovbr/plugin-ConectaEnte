<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\Log;
use ConectaEnte\Http\HttpExchange;
use ConectaEnte\Plugin;
use ConectaEnte\Services\SendHistory;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Subsite;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

/**
 * A rota que serve o histórico de envio de uma oportunidade.
 */
class SendHistoryRouteTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    protected function tearDown(): void
    {
        $this->app->setCurrentSubsiteId(null);

        parent::tearDown();
    }

    function testGuestIsAskedToLogIn()
    {
        $this->assertStatus401($this->requestFactory->GET('conectaente', 'sendHistory', [1]));
    }

    function testUserWithoutTheGlobalRoleIsRefused()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $this->login($this->userDirector->createUser());

        $this->assertSame(403, $this->send($this->sendHistory($opportunity->id)));
    }

    // a permissão sobre o edital não abre o histórico dele: o payload é do estado, não de quem edita
    function testAdminWhoCanEditTheOpportunityStillDoesNotReachTheHistory()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $this->openSend($opportunity);

        $this->login($this->userDirector->createUser(['admin']));

        $this->assertTrue($opportunity->canUser('modify'), 'O caso só prova o que promete se este administrador puder editar a oportunidade.');
        $this->assertSame(403, $this->send($this->sendHistory($opportunity->id)));
    }

    // o papel é conferido antes de procurar a oportunidade: para quem não o tem, nem a existência dela aparece
    function testTheRoleIsCheckedBeforeTheOpportunityIsLookedUp()
    {
        $this->login($this->userDirector->createUser());

        $this->assertSame(403, $this->send($this->sendHistory(999999)));
    }

    function testTheSaasSuperAdminReadsTheHistory()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $this->openSend($opportunity);

        $this->assertSame(200, $this->send($this->sendHistory($opportunity->id)));
        $this->assertCount(1, $this->responseJson()['sends']);
    }

    // o buraco do CultEditais: lá, o administrador de uma instalação lê o histórico de outra informando o id
    function testTheAdminOfAnotherSubsiteDoesNotReachTheHistory()
    {
        $this->login($this->userDirector->createUser(['saasSuperAdmin']));
        $instalacaoDoEdital = $this->createSubsite('edital.exemplo');
        $this->app->setCurrentSubsiteId($instalacaoDoEdital->id);
        $opportunity = $this->createOpportunity();
        $this->openSend($opportunity);

        $this->login($this->userDirector->createUser(['saasSuperAdmin']));
        $this->app->setCurrentSubsiteId($this->createSubsite('outra.exemplo')->id);

        $this->assertSame(404, $this->send($this->sendHistory($opportunity->id)), 'O payload guardado tem o documento do Ente dentro.');
    }

    function testTheSendsComeNewestFirstWithTheAttemptsNested()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $primeiro = $this->openSend($opportunity, status: 500, outcome: SendOutcome::ERROR);
        $segundo = $this->openSend($opportunity, status: 200, outcome: SendOutcome::SUCCESS);

        $this->send($this->sendHistory($opportunity->id));

        $sends = $this->responseJson()['sends'];
        $this->assertSame([$segundo->uuid, $primeiro->uuid], array_column($sends, 'uuid'), 'O mais recente primeiro: é a ordem em que a tela lê.');
        $this->assertSame(200, $sends[0]['attempts'][0]['httpStatus']);
    }

    // os quatro nascem no mesmo segundo: o carimbo empata, e só o id decide quem vem antes
    function testThePagesSliceTheHistoryInTheOrderOfTheIds()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $uuids = [];

        foreach (range(1, 4) as $ignored) {
            $uuids[] = $this->openSend($opportunity)->uuid;
        }

        $primeiraPagina = array_column($this->historyPage($opportunity, page: 1, pageSize: 2)['sends'], 'uuid');
        $segundaPagina = array_column($this->historyPage($opportunity, page: 2, pageSize: 2)['sends'], 'uuid');

        $this->assertSame([$uuids[3], $uuids[2]], $primeiraPagina, 'Sem desempate, a ordem entre carimbos iguais é a que o banco quiser.');
        $this->assertSame([$uuids[1], $uuids[0]], $segundaPagina, 'E aí o mesmo envio pode aparecer nas duas páginas.');
    }

    function testThePageSizeHasACeiling()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $this->openSend($opportunity);

        $pagina = $this->historyPage($opportunity, page: 1, pageSize: 5000);

        $this->assertSame(SendHistory::MAX_PAGE_SIZE, $pagina['pageSize'], 'Sem teto, um pedido grande varre a tabela inteira.');
    }

    function testTheResponseCarriesNoRequestHeader()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $this->openSend($opportunity);

        $this->send($this->sendHistory($opportunity->id));

        $this->assertStringNotContainsString('requestHeaders', (string) $this->app->response->getBody(), 'O cabeçalho de requisição é onde o token viaja, e por isso não é gravado nem servido.');
    }

    private function openSend(Opportunity $opportunity, ?int $status = 200, string $outcome = SendOutcome::SUCCESS): Log
    {
        $logger = Plugin::instance()->sendLogger();
        $log = $logger->startOrResume($opportunity);
        $logger->recordAttempts(
            $log,
            [new HttpExchange(endpoint: 'https://cultbr.example/api/v1/oportunidades/', status: $status)],
            $outcome,
            attempt: 1,
            maxAttempts: 3,
        );
        $logger->finish($log, $outcome);

        return $log;
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

    private function historyPage(Opportunity $opportunity, int $page, int $pageSize): array
    {
        $this->send($this->requestFactory->GET('conectaente', 'sendHistory', [$opportunity->id], compact('page', 'pageSize')));

        return $this->responseJson();
    }

    private function sendHistory(int $opportunityId): ServerRequestInterface
    {
        return $this->requestFactory->GET('conectaente', 'sendHistory', [$opportunityId]);
    }

    private function responseJson(): array
    {
        return json_decode((string) $this->app->response->getBody(), true)['data'] ?? json_decode((string) $this->app->response->getBody(), true);
    }
}
