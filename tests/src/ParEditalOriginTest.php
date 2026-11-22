<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Http\HttpExchange;
use ConectaEnte\Jobs\SendOpportunityJob;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Migrations\RecoverParEditalOrigin;
use ConectaEnte\Plugin;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\User;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\ConectaEnte\Traits\SendQueue;

/**
 * O tipo de ação e o Ente Federado de cada edital enviado: gravados no envio e recuperados do histórico.
 */
class ParEditalOriginTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use SendQueue;

    const ECHO_WITH_ACTION = ['id_par_edital' => 53781, 'acao' => ['id_par_tipo_acao' => 1, 'nome_acao' => '1.1 Fomento Cultural']];

    function testAnAcceptedSendKeepsTheActionTypeAndTheDocumentOfTheSender()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(200, self::ECHO_WITH_ACTION);

        $this->executeSend($opportunity->id);

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame('1', $reloaded->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID), 'A estrutura do formulário de resultado é pedida por tipo de ação.');
        $this->assertSame($this->resolveFederativeEntity($opportunity)->document, $reloaded->getMetadata(CultBrMetadata::PAR_EDITAL_FEDERATIVE_ENTITY_DOCUMENT));
    }

    function testAnEchoWithoutTheActionKeepsTheTypeAnEarlierSendBrought()
    {
        $opportunity = $this->liveSealedOpportunity();
        $opportunity->setMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID, '5');
        $opportunity->saveMetadata();
        Plugin::instance()->transport = FakeTransport::replying(200, ['id_par_edital' => 53781]);

        $this->executeSend($opportunity->id);

        $this->assertSame('5', $this->reloaded($opportunity)->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID));
    }

    function testAnEchoWithoutTheActionWritesNoType()
    {
        $opportunity = $this->liveSealedOpportunity();
        Plugin::instance()->transport = FakeTransport::replying(200, ['id_par_edital' => 53781, 'acao' => ['id_par_tipo_acao' => '1']]);

        $this->executeSend($opportunity->id);

        $this->assertNull($this->reloaded($opportunity)->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID), 'Tipo em texto não é o que o contrato devolve.');
    }

    function testTheMigrationTakesTheDocumentFromThePayloadNotFromTheSealOfToday()
    {
        $this->loginAsSaasSuperAdmin();
        $sender = $this->createFederativeEntity('Município que enviou', '11222333000181');
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->acceptedAttempt($opportunity, 53781, $sender->document);

        RecoverParEditalOrigin::run();

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame('11222333000181', $reloaded->getMetadata(CultBrMetadata::PAR_EDITAL_FEDERATIVE_ENTITY_DOCUMENT), 'Pelo selo de hoje, o edital que trocou de Ente Federado nunca seria barrado.');
        $this->assertSame('1', $reloaded->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID));
    }

    function testTheMigrationRestoresAMissingParEditalId()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->acceptedAttempt($opportunity, 53786, $this->resolveFederativeEntity($opportunity)->document);

        RecoverParEditalOrigin::run();

        $this->assertSame('53786', $this->reloaded($opportunity)->getMetadata(CultBrMetadata::PAR_EDITAL_ID), 'É o caso do 12461, aceito no histórico e sem o id gravado.');
    }

    function testTheMigrationKeepsWhatIsAlreadyStored()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $opportunity->setMetadata(CultBrMetadata::PAR_EDITAL_ID, '99999');
        $opportunity->setMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID, '7');
        $opportunity->saveMetadata();
        $this->acceptedAttempt($opportunity, 53781, $this->resolveFederativeEntity($opportunity)->document);

        RecoverParEditalOrigin::run();

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame('99999', $reloaded->getMetadata(CultBrMetadata::PAR_EDITAL_ID));
        $this->assertSame('7', $reloaded->getMetadata(CultBrMetadata::PAR_ACTION_TYPE_ID));
    }

    function testTheMigrationDoesNotGiveAnOwnedIdToASecondOpportunity()
    {
        $this->loginAsSaasSuperAdmin();
        $owner = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $owner->setMetadata(CultBrMetadata::PAR_EDITAL_ID, '53781');
        $owner->saveMetadata();
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->acceptedAttempt($opportunity, 53781, $this->resolveFederativeEntity($opportunity)->document);

        RecoverParEditalOrigin::run();

        $this->assertNull($this->reloaded($opportunity)->getMetadata(CultBrMetadata::PAR_EDITAL_ID));
    }

    function testTheMigrationIgnoresADocumentWithoutARegisteredFederativeEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->acceptedAttempt($opportunity, 53781, '99888777000166');

        RecoverParEditalOrigin::run();

        $this->assertNull($this->reloaded($opportunity)->getMetadata(CultBrMetadata::PAR_EDITAL_FEDERATIVE_ENTITY_DOCUMENT));
    }

    function testTheMigrationDoesNotResendTheEdital()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        Plugin::instance()->mode = Plugin::MODE_LIVE;
        $this->acceptedAttempt($opportunity, 53786, $this->resolveFederativeEntity($opportunity)->document);
        $this->purgeSendJobs();

        RecoverParEditalOrigin::run();

        $this->assertSame([], $this->enqueuedSendJobs(), 'Salvar a oportunidade selada a reenviaria ao CultBR.');
    }

    private function acceptedAttempt(Opportunity $opportunity, int $parEditalId, string $document): void
    {
        $this->login($this->app->repo(User::class)->find($this->app->user->id));
        $log = Plugin::instance()->sendLogger()->startOrResume($opportunity);
        $exchange = new HttpExchange(
            endpoint: 'https://cultbr.example/api/v1/oportunidades/',
            status: 200,
            requestPayload: ['id' => $opportunity->id, 'ente_federado' => ['cnpj' => $document, 'nome' => 'Ente']],
            responseBody: json_encode(['id_par_edital' => $parEditalId, 'acao' => ['id_par_tipo_acao' => 1]]),
        );
        Plugin::instance()->sendLogger()->recordAttempt($log, $exchange, SendOutcome::SUCCESS, 1, 3);
        $this->app->em->clear();
    }

    private function liveSealedOpportunity(): Opportunity
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        Plugin::instance()->mode = Plugin::MODE_LIVE;
        $this->purgeSendJobs();

        return $opportunity;
    }

    private function executeSend(int $opportunityId): void
    {
        $jobType = new SendOpportunityJob(SendOpportunityJob::SLUG);
        $job = new Job($jobType);
        $job->opportunityId = $opportunityId;
        $job->attempt = 1;

        $jobType->_execute($job);
    }

    protected function tearDown(): void
    {
        Plugin::instance()->transport = null;

        parent::tearDown();
    }
}
