<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\Log;
use ConectaEnte\Http\HttpExchange;
use ConectaEnte\Services\SendLogger;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\CapturesLog;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\ConectaEnte\Traits\SendQueue;

/**
 * O ciclo do histórico de um envio: abertura, tentativa, fechamento e as guardas de cada um.
 */
class SendLoggerTest extends TestCase
{
    use CapturesLog;
    use PublicationRequirementsFixtures;
    use SendQueue;

    protected function tearDown(): void
    {
        $this->popCapturedLog();

        parent::tearDown();
    }

    function testTheEnvelopeOpensPendingForTheOpportunity()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $log = $this->logger()->startOrResume($opportunity);

        $this->assertSame(SendOutcome::PENDING, $log->outcome);
        $this->assertSame($opportunity->id, $log->opportunityId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $log->uuid,
            'O uuid viaja no payload do job e é a chave do envio: formato previsível, não sequência adivinhável.'
        );
        $this->assertSame($this->app->user->id, $log->triggeredByUser->id);
    }

    function testTheRetryResumesTheSameEnvelope()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $first = $this->logger()->startOrResume($opportunity);

        $resumed = $this->logger()->startOrResume($opportunity, $first->uuid);

        $this->assertSame($first->id, $resumed->id);
        $this->assertSame(1, $this->storedLogCountOf($opportunity), 'A retentativa entra no envio que já existe, não abre outro.');
    }

    function testTheResumedEnvelopeKeepsTheFirstAuthor()
    {
        $author = $this->userDirector->createUser(['saasSuperAdmin']);
        $this->login($author);
        $opportunity = $this->createOpportunity();
        $first = $this->logger()->startOrResume($opportunity);

        $this->login($this->userDirector->createUser(['saasSuperAdmin']));
        $resumed = $this->logger()->startOrResume($opportunity, $first->uuid);

        $this->assertSame($author->id, $resumed->triggeredByUser->id, 'Quem disparou é quem salvou; a retentativa não reescreve o autor.');
    }

    function testTheEnvelopeHasNoAuthorWhenThereIsNoSession()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $this->logout();

        $log = $this->logger()->startOrResume($opportunity);

        $this->assertNull($log->triggeredByUser, 'No worker não há sessão, e o visitante do core não é um usuário gravável.');
        $this->assertSame(SendOutcome::PENDING, $log->outcome);
    }

    function testOpeningAnEnvelopeAbandonsThePreviousPendingOne()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $abandoned = $this->logger()->startOrResume($opportunity);

        $current = $this->logger()->startOrResume($opportunity);

        $this->assertSame(SendOutcome::ABANDONED, $this->storedOutcomeOf($abandoned));
        $this->assertSame(SendOutcome::PENDING, $this->storedOutcomeOf($current), 'O abandono roda depois do insert e não pode alcançar o envio que acabou de nascer.');
    }

    function testOpeningAnEnvelopeLeavesTheOtherOpportunityAlone()
    {
        $this->loginAsSaasSuperAdmin();
        $other = $this->logger()->startOrResume($this->createOpportunity());

        $this->logger()->startOrResume($this->createOpportunity());

        $this->assertSame(SendOutcome::PENDING, $this->storedOutcomeOf($other), 'O abandono é por oportunidade: alcançar outras apagaria histórico em curso alheio.');
    }

    function testOpeningAnEnvelopeDoesNotTouchTheOnesAlreadyClosed()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $closed = $this->logger()->startOrResume($opportunity);
        $this->answeredAttemptOn($closed);
        $this->logger()->finish($closed, SendOutcome::SUCCESS);

        $this->logger()->startOrResume($opportunity);

        $this->assertSame(SendOutcome::SUCCESS, $this->storedOutcomeOf($closed), 'O abandono alcança só o que está em andamento: reescrever desfecho já fechado apagaria o histórico.');
    }

    function testTheUuidFromTheJobIsKeptWhenTheEnvelopeIsGone()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $log = $this->logger()->startOrResume($opportunity, 'c0ffee00-0000-4000-8000-000000000000');

        $this->assertSame('c0ffee00-0000-4000-8000-000000000000', $log->uuid, 'Se a abertura da primeira tentativa falhou, o uuid do job continua valendo para a retentativa.');
    }

    function testTheEnvelopeClosesWithTheOutcome()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());
        $this->answeredAttemptOn($log);

        $closed = $this->logger()->finish($log, SendOutcome::SUCCESS);

        $this->assertTrue($closed);
        $this->assertSame(SendOutcome::SUCCESS, $this->storedOutcomeOf($log));
        $this->assertSame(SendOutcome::SUCCESS, $log->outcome, 'O UPDATE não passa pelo UnitOfWork: sem recarregar, quem já tem o envio em mão continua lendo pendente.');
        $this->assertNotNull($log->updateTimestamp);
    }

    function testTheSecondCloseLosesTheRace()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());
        $this->answeredAttemptOn($log);
        $this->logger()->finish($log, SendOutcome::REJECTED);

        $second = $this->logger()->finish($log, SendOutcome::SUCCESS);

        $this->assertFalse($second);
        $this->assertSame(SendOutcome::REJECTED, $this->storedOutcomeOf($log), 'Quem chega depois não reescreve o desfecho: o primeiro fechamento é o que valeu.');
    }

    function testAnAbandonedEnvelopeIsNotRevivedBySuccess()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $abandoned = $this->logger()->startOrResume($opportunity);
        $this->answeredAttemptOn($abandoned);
        $this->logger()->startOrResume($opportunity);

        $closed = $this->logger()->finish($abandoned, SendOutcome::SUCCESS);

        $this->assertFalse($closed);
        $this->assertSame(SendOutcome::ABANDONED, $this->storedOutcomeOf($abandoned), 'Um worker lento não pode ressuscitar como sucesso o envio que um save mais novo abandonou.');
    }

    function testSuccessIsRefusedWithoutAnAnsweredAttempt()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());
        $this->logger()->recordAttempt($log, new HttpExchange(
            endpoint: 'https://cultbr.example/api/v1/oportunidades/',
            errorMessage: 'A Plataforma CultBR não respondeu.',
        ), SendOutcome::ERROR, attempt: 1, maxAttempts: 3);
        $capturedLog = $this->captureLog();

        $closed = $this->logger()->finish($log, SendOutcome::SUCCESS);

        $this->assertFalse($closed);
        $this->assertSame(SendOutcome::PENDING, $this->storedOutcomeOf($log), 'Sucesso sem requisição respondida é carimbo sem evidência.');
        $this->assertTrue($capturedLog->hasErrorThatContains('sem tentativa respondida'));
    }

    function testTheSimulatedSendClosesWithoutAnAnsweredAttempt()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());
        $this->logger()->recordAttempt($log, new HttpExchange(
            endpoint: 'https://cultbr.example/api/v1/oportunidades/',
        ), SendOutcome::SIMULATED, attempt: 1, maxAttempts: 3);

        $closed = $this->logger()->finish($log, SendOutcome::SIMULATED);

        $this->assertTrue($closed, 'Em modo simulado não há requisição, e a guarda de evidência vale só para sucesso.');
        $this->assertSame(SendOutcome::SIMULATED, $this->storedOutcomeOf($log));
    }

    function testAStateThatIsNotAnOutcomeDoesNotClose()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());
        $capturedLog = $this->captureLog();

        foreach ([SendOutcome::PENDING, SendOutcome::ABANDONED, 'enviado'] as $state) {
            $this->assertFalse($this->logger()->finish($log, $state), $state);
        }

        $this->assertSame(SendOutcome::PENDING, $this->storedOutcomeOf($log));
        $this->assertTrue($capturedLog->hasErrorThatContains("desfecho 'enviado' não fecha"));
    }

    private function logger(): SendLogger
    {
        return new SendLogger;
    }

    private function answeredAttemptOn(Log $log): void
    {
        $this->logger()->recordAttempt($log, new HttpExchange(
            endpoint: 'https://cultbr.example/api/v1/oportunidades/',
            status: 201,
        ), SendOutcome::SUCCESS, attempt: 1, maxAttempts: 3);
    }

    /** @return LogAttempt[] */
    // leitura pela conexão: o UPDATE condicional não passa pelo UnitOfWork, e o cache de identidade devolveria o valor antigo
    private function storedOutcomeOf(Log $log): string
    {
        return (string) $this->app->em->getConnection()->fetchOne(
            'SELECT outcome FROM conectaente_log WHERE id = ?',
            [$log->id]
        );
    }

    private function storedLogCountOf(Opportunity $opportunity): int
    {
        return (int) $this->app->em->getConnection()->fetchOne(
            'SELECT count(*) FROM conectaente_log WHERE opportunity_id = ?',
            [$opportunity->id]
        );
    }
}
