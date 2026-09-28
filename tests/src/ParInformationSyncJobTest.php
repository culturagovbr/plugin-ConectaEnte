<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Jobs\ParInformationFetchJob;
use ConectaEnte\Jobs\ParInformationSyncJob;
use ConectaEnte\Plugin;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Entities\SealRelation;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;

/**
 * O job que escolhe de quais Entes Federados a árvore do PAR vale a pena buscar.
 */
class ParInformationSyncJobTest extends TestCase
{
    use ConectaEnteFixtures;

    function testEnqueuesOneFetchPerEntitySealingALiveOpportunity()
    {
        $this->loginAsSaasSuperAdmin();
        $first = $this->entitySealing(Opportunity::STATUS_ENABLED, 'Município A', '12200176000176');
        $second = $this->entitySealing(Opportunity::STATUS_ENABLED, 'Município B', '82951229000176');
        $this->purgeFetchJobs();

        $this->executeSync();

        $this->assertSame($this->sorted([$first->id, $second->id]), $this->enqueuedEntityIds());
    }

    function testTheFetchesAreSpacedSoTheyDoNotHogTheQueue()
    {
        $this->loginAsSaasSuperAdmin();
        $this->entitySealing(Opportunity::STATUS_ENABLED, 'Município A', '12200176000176');
        $this->entitySealing(Opportunity::STATUS_ENABLED, 'Município B', '82951229000176');
        $this->purgeFetchJobs();

        $this->executeSync();

        $starts = array_map(fn(Job $job) => $job->nextExecutionTimestamp->getTimestamp(), $this->enqueuedFetchJobs());
        sort($starts);

        $this->assertSame(ParInformationSyncJob::FETCH_SPACING_SECONDS, $starts[1] - $starts[0], 'Todas para agora, elas passam na frente de qualquer outro job da instalação.');
    }

    function testTheSelectionDoesNotTouchTheApi()
    {
        $this->loginAsSaasSuperAdmin();
        $this->entitySealing(Opportunity::STATUS_ENABLED);
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, ['data' => []]);
        $this->purgeFetchJobs();

        $this->executeSync();

        $this->assertSame([], $transport->requestedUrls, 'A seleção só enfileira: quem fala com o CultBR é o job de cada ente.');
    }

    function testADraftOpportunityCountsAsLive()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->entitySealing(Opportunity::STATUS_DRAFT);
        $this->purgeFetchJobs();

        $this->executeSync();

        $this->assertSame([$federativeEntity->id], $this->enqueuedEntityIds(), 'É no rascunho que o gestor escolhe o PAR para publicar.');
    }

    function testAnEntityWithoutAnySealIsLeftOut()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntity();
        $this->purgeFetchJobs();

        $this->executeSync();

        $this->assertSame([], $this->enqueuedEntityIds());
    }

    function testAnEntityWhoseSealNobodyAppliedIsLeftOut()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntityWithSeal($this->createSeal());
        $this->purgeFetchJobs();

        $this->executeSync();

        $this->assertSame([], $this->enqueuedEntityIds(), 'Selo cadastrado e nunca concedido não tem quem abra a árvore.');
    }

    function testAnEntityWhoseOnlyOpportunityIsInTheTrashIsLeftOut()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $this->createOpportunityWithSeal($seal)->delete(true);
        $this->purgeFetchJobs();

        $this->executeSync();

        $this->assertSame([], $this->enqueuedEntityIds());
    }

    function testAnEntityWithAPendingSealRelationIsLeftOut()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $opportunity = $this->createOpportunityWithSeal($seal);
        $relation = $opportunity->getSealRelations()[0];
        $relation->status = SealRelation::STATUS_PENDING;
        $relation->save(true);
        $this->purgeFetchJobs();

        $this->executeSync();

        $this->assertSame([], $this->enqueuedEntityIds(), 'Relação pendente não sela a oportunidade, então não há campo do CultBR para preencher.');
    }

    function testAnEntityWithATrashedSealIsLeftOut()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal);
        $this->createOpportunityWithSeal($seal);
        $seal->status = Seal::STATUS_TRASH;
        $seal->save(true);
        $this->purgeFetchJobs();

        $this->executeSync();

        $this->assertSame([], $this->enqueuedEntityIds());
    }

    function testATrashedEntityIsLeftOut()
    {
        $this->loginAsSaasSuperAdmin();
        $this->entitySealing(Opportunity::STATUS_ENABLED)->delete(true);
        $this->purgeFetchJobs();

        $this->executeSync();

        $this->assertSame([], $this->enqueuedEntityIds());
    }

    function testEnqueueIsIdempotentThanksToTheDeterministicId()
    {
        $this->purgeScheduledSync();

        Plugin::instance()->scheduleParSync();
        Plugin::instance()->scheduleParSync();

        $jobs = $this->app->repo(Job::class)->findBy(['id' => $this->scheduledSyncId()]);

        $this->assertCount(1, $jobs);
        $this->assertSame(1, $jobs[0]->iterations, 'Uma iteração por agendamento: é o que deixa a limpeza de job preso do core funcionar.');
        $this->assertGreaterThan(new \DateTime('+1 minute'), $jobs[0]->nextExecutionTimestamp, 'O agendado de rotina espera o intervalo; imediato é só o do ente salvo.');
    }

    function testSavingAFederativeEntityEnqueuesOnlyItsOwnFetch()
    {
        $this->loginAsSaasSuperAdmin();
        $other = $this->createFederativeEntity('Município A', '12200176000176');
        $this->purgeFetchJobs();

        $saved = $this->createFederativeEntity('Município B', '82951229000176');

        $this->assertSame([$saved->id], $this->enqueuedEntityIds(), 'Trocar o token de um ente não pode disparar a busca do cadastro inteiro.');
        $this->assertNotSame($other->id, $saved->id);
        $this->assertLessThanOrEqual(new \DateTime(), $this->enqueuedFetchJobs()[0]->nextExecutionTimestamp, 'Token novo não espera o próximo ciclo.');
    }

    function testSendingAnEntityToTheTrashDoesNotEnqueueAFetch()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        $this->purgeFetchJobs();

        $federativeEntity->delete(true);

        $this->assertSame([], $this->enqueuedEntityIds(), 'Ente excluído não tem árvore para aquecer.');
    }

    function testSavingAnEntityDuringItsRunningFetchDoesNotDisturbIt()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        $running = $this->enqueuedFetchJobs()[0];
        $running->status = Job::STATUS_PROCESSING;
        $running->save(true);

        $federativeEntity->name = 'Município renomeado';
        $federativeEntity->save(true);

        $after = $this->enqueuedFetchJobs()[0];

        $this->assertSame($running->pk, $after->pk, 'Substituir o job em execução derrubaria o worker que o carrega.');
        $this->assertSame(Job::STATUS_PROCESSING, $after->status);
    }

    private function entitySealing(int $opportunityStatus, string $name = 'Governo de Santa Catarina', string $document = '12345678000190'): FederativeEntity
    {
        $seal = $this->createSeal();
        $federativeEntity = $this->createFederativeEntityWithSeal($seal, $name, $document);
        $this->createOpportunityWithSeal($seal, $opportunityStatus);

        return $federativeEntity;
    }

    /** @return Job[] */
    private function enqueuedFetchJobs(): array
    {
        return $this->app->repo(Job::class)->findBy(['type' => ParInformationFetchJob::SLUG]);
    }

    /** @return int[] */
    private function enqueuedEntityIds(): array
    {
        return $this->sorted(array_map(fn(Job $job) => (int) $job->federativeEntityId, $this->enqueuedFetchJobs()));
    }

    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }

    // criar um ente já enfileira a busca dele: cada cenário conta só o que o job de seleção deixou
    private function purgeFetchJobs(): void
    {
        $this->app->em->getConnection()->delete('job', ['name' => ParInformationFetchJob::SLUG]);
    }

    private function scheduledSyncId(): string
    {
        return md5(ParInformationSyncJob::SLUG . ':' . ParInformationSyncJob::SLUG);
    }

    // o dump de testes carrega o agendamento antigo; cada cenário parte do zero
    private function purgeScheduledSync(): void
    {
        $this->app->em->getConnection()->delete('job', ['id' => $this->scheduledSyncId()]);
    }

    private function executeSync(): void
    {
        $jobType = new ParInformationSyncJob(ParInformationSyncJob::SLUG);
        $jobType->_execute(new Job($jobType));
    }
}
