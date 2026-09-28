<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use MapasCulturais\Entities\Opportunity;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

/**
 * A rota que serve a árvore do PAR à cascata da aba, sempre a partir do cache.
 */
class ParInformationRouteTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    function testGuestIsAskedToLogIn()
    {
        $this->assertStatus401($this->requestFactory->GET('conectaente', 'parInformation', [1]));
    }

    function testUserWhoCannotEditTheOpportunityIsRefused()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $this->login($this->userDirector->createUser());

        $this->assertSame(403, $this->send($this->parInformation($opportunity->id)));
    }

    function testMissingOpportunityIsNotFound()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertSame(404, $this->send($this->parInformation(999999)));
    }

    function testOpportunityWithoutASealAnswersAvailableWithAnEmptyTree()
    {
        $opportunity = $this->coreCompleteOpportunity(Opportunity::STATUS_DRAFT);

        $this->assertSame(200, $this->send($this->parInformation($opportunity->id)));

        $this->assertSame(['available' => true, 'exercicios' => []], $this->responseJson(), 'Sem Ente Federado não há PAR a mostrar — e isso não é indisponibilidade.');
    }

    function testPhaseOfASealedOpportunityAnswersAsUnsealed()
    {
        $phase = $this->sealedOpportunity(Opportunity::STATUS_ENABLED)->lastPhase;

        $this->assertSame(200, $this->send($this->parInformation($phase->id)));

        $this->assertSame(['available' => true, 'exercicios' => []], $this->responseJson(), 'O selo é da raiz; a fase não tem PAR próprio.');
    }

    function testSealedOpportunityGetsTheCachedTree()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $this->primeParInformationCache($this->resolveFederativeEntity($opportunity), [
            ['id' => '2024', 'ano' => '2024', 'metas' => [['id' => 'm1', 'nome' => 'Meta 1', 'acoes' => []]]],
        ]);

        $this->assertSame(200, $this->send($this->parInformation($opportunity->id)));

        $body = $this->responseJson();
        $this->assertTrue($body['available']);
        $this->assertSame('2024', $body['exercicios'][0]['ano']);
        $this->assertSame(['id', 'nome', 'valor', 'acoes'], array_keys($body['exercicios'][0]['metas'][0]));
    }

    function testEmptyCacheGetsTheTreeFromTheApi()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $federativeEntity = $this->resolveFederativeEntity($opportunity);
        $this->app->mscache->delete(\ConectaEnte\Services\ParInformationService::cacheKey($federativeEntity));
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, ['data' => [
            ['cnpj' => $federativeEntity->document, 'exercicios' => [['id' => '2026', 'ano' => '2026']]],
        ]]);

        $this->assertSame(200, $this->send($this->parInformation($opportunity->id)));

        $body = $this->responseJson();
        $this->assertTrue($body['available'], 'Cache frio não vira aviso de indisponibilidade na tela.');
        $this->assertSame('2026', $body['exercicios'][0]['ano']);
        $this->assertCount(1, $transport->requestedUrls);
    }

    function testApiOutOfReachWithNothingCachedAnswersUnavailable()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $this->app->mscache->delete(\ConectaEnte\Services\ParInformationService::cacheKey($this->resolveFederativeEntity($opportunity)));
        Plugin::instance()->transport = FakeTransport::unreachable();

        $this->assertSame(200, $this->send($this->parInformation($opportunity->id)));

        $this->assertSame(
            ['available' => false, 'exercicios' => []],
            $this->responseJson(),
            'Sem cache e sem API, a tela avisa em vez de mostrar lista vazia.',
        );
    }

    function testCachedNotFoundAnswersUnavailable()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $this->primeParInformationNotFound($this->resolveFederativeEntity($opportunity));
        // o cache vazio responderia igual: sem esta pré-condição, o teste passaria com o prime quebrado
        $this->assertTrue(Plugin::instance()->parInformationService()->getForOpportunity($opportunity)->notFound);

        $this->assertSame(200, $this->send($this->parInformation($opportunity->id)));

        $this->assertSame(
            ['available' => false, 'exercicios' => []],
            $this->responseJson(),
            'Ambiente sem a rota do PAR não é "ente sem dados".',
        );
    }

    function testNodeWithoutANameKeepsItsIdInTheJson()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $this->primeParInformationCache($this->resolveFederativeEntity($opportunity), [
            ['id' => '2024', 'metas' => [['id' => 'm1']]],
        ]);

        $this->send($this->parInformation($opportunity->id));

        $goal = $this->responseJson()['exercicios'][0]['metas'][0];
        $this->assertSame('m1', $goal['id'], 'É o id que a tela usa como rótulo quando o nome vem nulo.');
        $this->assertNull($goal['nome']);
    }

    // o Plugin é singleton do processo: transporte e serviço trocados aqui vazariam para os testes seguintes
    protected function tearDown(): void
    {
        Plugin::instance()->transport = null;

        parent::tearDown();
    }

    private function parInformation(int $opportunityId): ServerRequestInterface
    {
        return $this->requestFactory->GET('conectaente', 'parInformation', [$opportunityId], ajax: true);
    }

    private function responseJson(): array
    {
        return json_decode((string) $this->app->response->getBody(), true);
    }
}
