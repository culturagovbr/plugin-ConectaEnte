<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\Log;
use ConectaEnte\Entities\LogAttempt;
use ConectaEnte\Http\HttpExchange;
use ConectaEnte\Jobs\SendOpportunityJob;
use ConectaEnte\Plugin;
use ConectaEnte\Services\SendLogger;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
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

    function testTheAttemptIsStoredUnderTheEnvelope()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());

        $this->logger()->recordAttempt($log, new HttpExchange(
            endpoint: 'https://cultbr.example/api/v1/oportunidades/',
            method: 'POST',
            status: 201,
            requestId: 'req-7',
            durationMs: 142,
        ), SendOutcome::SUCCESS, attempt: 2, maxAttempts: 3);

        $stored = $this->attemptsOf($log)[0];
        $this->assertSame(2, $stored->attempt);
        $this->assertSame(3, $stored->maxAttempts);
        $this->assertSame('https://cultbr.example/api/v1/oportunidades/', $stored->endpoint);
        $this->assertSame('POST', $stored->httpMethod);
        $this->assertSame(201, $stored->httpStatus);
        $this->assertSame('req-7', $stored->requestId);
        $this->assertSame(142, $stored->durationMs);
        $this->assertSame(SendOutcome::SUCCESS, $stored->outcome);
    }

    function testEveryExchangeOfTheSameRunBecomesItsOwnAttempt()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());
        $recusada = new HttpExchange(endpoint: 'https://cultbr.example/api/v1/oportunidades/53781', method: 'PUT', status: 404);
        $aceita = new HttpExchange(endpoint: 'https://cultbr.example/api/v1/oportunidades/', method: 'POST', status: 201);

        $this->logger()->recordAttempts($log, [$recusada, $aceita], SendOutcome::SUCCESS, attempt: 2, maxAttempts: 3);

        $armazenadas = $this->attemptsOf($log);
        $this->assertCount(2, $armazenadas, 'Uma linha por troca: sobrescrever faria a requisição recusada sumir do histórico.');
        $this->assertSame(['PUT', 'POST'], array_map(fn ($tentativa) => $tentativa->httpMethod, $armazenadas));
        $this->assertSame([404, 201], array_map(fn ($tentativa) => $tentativa->httpStatus, $armazenadas));
        $this->assertSame([2, 2], array_map(fn ($tentativa) => $tentativa->attempt, $armazenadas), 'As duas trocas são da mesma tentativa do job.');
    }

    function testThePayloadAndHeadersAreStoredAsReadableJson()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());

        $this->logger()->recordAttempt($log, new HttpExchange(
            endpoint: 'https://cultbr.example/api/v1/oportunidades/',
            requestPayload: ['nome' => 'Edital de Ação Cultural'],
            responseHeaders: ['x-request-id' => 'req-7'],
        ), SendOutcome::SUCCESS, attempt: 1, maxAttempts: 3);

        $stored = $this->attemptsOf($log)[0];
        $this->assertStringContainsString('Ação', $stored->requestPayload, 'Acento escapado em \\u torna a coluna ilegível para quem for depurar.');
        $this->assertSame(['nome' => 'Edital de Ação Cultural'], json_decode($stored->requestPayload, true));
        $this->assertSame(['x-request-id' => 'req-7'], json_decode($stored->responseHeaders, true));
    }

    function testAnAttemptWithoutRequestKeepsStatusAndDurationNull()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());

        $this->logger()->recordAttempt($log, new HttpExchange(
            endpoint: 'https://cultbr.example/api/v1/oportunidades/',
            errorMessage: 'O payload do edital está incompleto.',
        ), SendOutcome::ERROR, attempt: 1, maxAttempts: 3);

        $stored = $this->attemptsOf($log)[0];
        $this->assertNull($stored->httpStatus, 'Falha anterior à requisição é tentativa sem status: inventar um seria mentir sobre a resposta.');
        $this->assertNull($stored->durationMs);
        $this->assertNull($stored->requestPayload);
        $this->assertSame('O payload do edital está incompleto.', $stored->errorMessage);
    }

    function testInvalidUtf8BecomesTheReplacementCharacter()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());

        $attempt = $this->logger()->recordAttempt($log, new HttpExchange(
            endpoint: 'https://cultbr.example/api/v1/oportunidades/',
            status: 502,
            responseBody: "<html>erro \xC3\x28 do proxy</html>",
        ), SendOutcome::ERROR, attempt: 1, maxAttempts: 3);

        $this->assertNotNull($attempt, 'O Postgres recusa byte inválido até em coluna text: sem o saneamento, o insert derruba o envio.');
        $this->assertStringContainsString("\u{FFFD}", $this->attemptsOf($log)[0]->responseBody);
    }

    function testTheStoredResponseIsCapped()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());

        $this->logger()->recordAttempt($log, new HttpExchange(
            endpoint: 'https://cultbr.example/api/v1/oportunidades/',
            status: 500,
            responseBody: str_repeat('a', SendLogger::MAX_STORED_BYTES + 4096),
        ), SendOutcome::ERROR, attempt: 1, maxAttempts: 3);

        $this->assertSame(
            SendLogger::MAX_STORED_BYTES,
            strlen($this->attemptsOf($log)[0]->responseBody),
            'Sem teto, uma página de erro grande entra inteira na linha, e a tabela já cresce sem expurgo.'
        );
    }

    function testAnOversizedPayloadIsStoredAsAMarkerInsteadOfBrokenJson()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());
        $campos = array_fill_keys(array_map(fn ($i) => "campo{$i}", range(1, 40)), str_repeat('x', 2000));

        $this->logger()->recordAttempt($log, new HttpExchange(
            endpoint: 'https://cultbr.example/api/v1/oportunidades/',
            status: 201,
            requestPayload: $campos,
        ), SendOutcome::SUCCESS, attempt: 1, maxAttempts: 3);

        $armazenado = $this->attemptsOf($log)[0]->requestPayload;
        $this->assertNotNull(json_decode($armazenado), 'Cortar o JSON por bytes grava texto que ninguém decodifica de volta.');
        $this->assertTrue(json_decode($armazenado, true)['truncated']);
    }

    function testTheRequestIdIsCappedToTheColumnWidth()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());

        $attempt = $this->logger()->recordAttempt($log, new HttpExchange(
            endpoint: 'https://cultbr.example/api/v1/oportunidades/',
            status: 201,
            requestId: str_repeat('x', 500),
        ), SendOutcome::SUCCESS, attempt: 1, maxAttempts: 3);

        $this->assertNotNull($attempt, 'O cabeçalho vem de fora: mais largo que a coluna, o insert falharia.');
        $this->assertSame(SendLogger::MAX_REQUEST_ID_BYTES, strlen($this->attemptsOf($log)[0]->requestId));
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

    function testTheFirstAttemptCarriesNoEnvelopeUuid()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();

        $this->assertArrayNotHasKey('logUuid', SendOpportunityJob::dataFor($opportunity));
    }

    function testTheRetryCarriesTheEnvelopeUuidInTheQueuedJob()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        Plugin::instance()->mode = Plugin::MODE_LIVE;
        $this->purgeSendJobs();

        Plugin::instance()->scheduleSend($opportunity, 2, 'now', 'c0ffee00-0000-4000-8000-000000000000');

        $jobs = $this->enqueuedSendJobs();
        $this->assertCount(1, $jobs, 'Premissa do teste: a retentativa precisa ter sido enfileirada.');
        $this->assertSame('c0ffee00-0000-4000-8000-000000000000', $jobs[0]->logUuid, 'Sem o uuid na fila, a retentativa abriria um envio novo e partiria o histórico em dois.');
    }

    function testTheRetryOfARunningJobCarriesTheUuidForward()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        Plugin::instance()->mode = Plugin::MODE_LIVE;
        Plugin::instance()->transport = FakeTransport::replying(500, 'Internal Server Error');
        $this->purgeSendJobs();

        $this->executeSendJob($opportunity->id, attempt: 1, logUuid: 'c0ffee00-0000-4000-8000-000000000000');

        $jobs = $this->enqueuedSendJobs();
        $this->assertCount(1, $jobs, 'Premissa do teste: a indisponibilidade precisa ter reagendado o envio.');
        $this->assertSame(
            'c0ffee00-0000-4000-8000-000000000000',
            $jobs[0]->logUuid,
            'O uuid tem que atravessar a retentativa: perdido aqui, a tentativa seguinte abre outro envio.'
        );
    }

    function testAFailureToStoreTheAttemptIsSwallowedAndLogged()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $log = new Log;
        $log->uuid = 'c0ffee00-0000-4000-8000-000000000001';
        $log->opportunityId = $opportunity->id;
        $log->outcome = SendOutcome::PENDING;
        $capturedLog = $this->captureLog();

        $attempt = $this->logger()->recordAttempt($log, new HttpExchange(
            endpoint: 'https://cultbr.example/api/v1/oportunidades/',
            status: 201,
        ), SendOutcome::SUCCESS, attempt: 1, maxAttempts: 3);

        $this->assertNull($attempt, 'Falha ao gravar o histórico não pode derrubar o envio.');
        $this->assertTrue($capturedLog->hasErrorThatContains("histórico do edital {$opportunity->id}"));
    }

    // engolir a falha não serve de nada se o Doctrine ficar inutilizável depois dela
    function testTheEntityManagerSurvivesAFailureToStore()
    {
        $this->loginAsSaasSuperAdmin();
        $log = $this->logger()->startOrResume($this->createOpportunity());

        $this->assertNotNull($log, 'Falha de gravação que feche o EntityManager derruba o resto da requisição, não só o histórico.');
    }

    private function executeSendJob(int $opportunityId, int $attempt, ?string $logUuid = null): void
    {
        $jobType = new SendOpportunityJob(SendOpportunityJob::SLUG);
        $job = new Job($jobType);
        $job->opportunityId = $opportunityId;
        $job->attempt = $attempt;
        $job->logUuid = $logUuid;

        $jobType->_execute($job);
    }

    // processo próprio: o EntityManager fechado não volta a abrir, e levaria junto todos os arquivos seguintes da suíte
    #[RunInSeparateProcess]
    function testAClosedEntityManagerStopsAtTheLoggerInstead()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity();
        $log = $this->logger()->startOrResume($opportunity);
        $this->app->log->setHandlers([$capturado = new TestHandler()]);
        $this->app->em->close();

        $aberto = $this->logger()->startOrResume($opportunity);
        $fechado = $this->logger()->finish($log, SendOutcome::SUCCESS);

        $this->assertNull($aberto, 'Abrir o envelope com o Doctrine fechado não pode derrubar o envio.');
        $this->assertFalse($fechado, 'Fechar o envelope com o Doctrine fechado também não.');
        $this->assertTrue($capturado->hasErrorThatContains("histórico do edital {$opportunity->id}"));
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
    private function attemptsOf(Log $log): array
    {
        return $this->app->repo(LogAttempt::class)->findBy(['log' => $log], ['id' => 'ASC']);
    }

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
