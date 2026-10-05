<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Jobs\SendOpportunityJob;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Seal;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * Os gatilhos que enfileiram o envio do edital ao CultBR.
 */
class SendTriggersTest extends TestCase
{
    use PublicationRequirementsFixtures;

    // `mode` é override opcional sobre a config: o neutro é null, não a string do modo
    protected function tearDown(): void
    {
        Plugin::instance()->transport = null;
        Plugin::instance()->mode = null;

        parent::tearDown();
    }

    function testSealingAPublishedOpportunityEnqueuesTheSend()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);

        $jobs = $this->enqueuedSendJobs();

        $this->assertCount(1, $jobs, 'Aplicar o selo do Ente Federado numa oportunidade publicada e completa precisa enfileirar o envio.');
        $this->assertSame($opportunity->id, (int) $jobs[0]->opportunityId, 'O job tem que apontar para a oportunidade que recebeu o selo.');
        $this->assertSame(1, (int) $jobs[0]->attempt, 'Disparo novo começa na primeira tentativa, não no meio da retentativa.');
    }

    // o gatilho dispara em toda relação de selo; quem barra o envio é a elegibilidade
    function testSealingAnUnpublishedOpportunityEnqueuesNothing()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);

        $this->assertSame(Opportunity::STATUS_DRAFT, (int) $opportunity->status, 'Premissa do teste: a oportunidade precisa estar mesmo em rascunho.');
        $this->assertNotNull($this->resolveFederativeEntity($opportunity), 'Premissa do teste: o selo precisa ter sido aplicado, ou o teste passaria por falta de selo.');
        $this->assertSame([], $this->enqueuedSendJobs(), 'Edital em rascunho não pode ir ao CultBR só porque ganhou o selo.');
    }

    function testPublishingASealedOpportunityEnqueuesTheSend()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $this->purgeSendJobs();
        $this->assertSame([], $this->enqueuedSendJobs(), 'Premissa do teste: a fila precisa começar vazia.');

        $opportunity->status = Opportunity::STATUS_ENABLED;
        $opportunity->save(true);

        $jobs = $this->enqueuedSendJobs();
        $this->assertCount(1, $jobs, 'Publicar o edital selado é o gatilho principal do envio.');
        $this->assertSame($opportunity->id, (int) $jobs[0]->opportunityId);
        $this->assertSame(Opportunity::STATUS_ENABLED, (int) $this->reloaded($opportunity)->status, 'Premissa do teste: a publicação precisa ter sido gravada.');
    }

    function testEditingAPublishedSealedOpportunityEnqueuesAgain()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->purgeSendJobs();
        $this->assertSame([], $this->enqueuedSendJobs(), 'Premissa do teste: a fila precisa começar vazia.');

        $opportunity->shortDescription = 'Edital revisado depois de publicado';
        $opportunity->save(true);

        $jobs = $this->enqueuedSendJobs();
        $this->assertCount(1, $jobs, 'Editar um edital já publicado precisa reenviar o que mudou.');
        $this->assertSame($opportunity->id, (int) $jobs[0]->opportunityId);
        $this->assertSame('Edital revisado depois de publicado', $this->reloaded($opportunity)->shortDescription, 'Premissa do teste: a edição precisa ter sido gravada.');
    }

    // sem dedupe por oportunidade, cada save do gestor viraria um envio na fila
    function testTwoSavesInARowLeaveASingleJobQueued()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->purgeSendJobs();
        $this->assertSame([], $this->enqueuedSendJobs(), 'Premissa do teste: a fila precisa começar vazia.');

        $opportunity->shortDescription = 'Primeira revisão';
        $opportunity->save(true);
        $firstPk = (int) $this->enqueuedSendJobs()[0]->pk;

        $opportunity->shortDescription = 'Segunda revisão';
        $opportunity->save(true);

        $jobs = $this->enqueuedSendJobs();
        $this->assertCount(1, $jobs, 'Dois saves da mesma oportunidade substituem o job, não acumulam.');
        $this->assertNotSame($firstPk, (int) $jobs[0]->pk, 'A linha é nova: contar um só não distingue substituição de segundo save que não disparou.');
        $this->assertSame('Segunda revisão', $this->reloaded($opportunity)->shortDescription, 'Premissa do teste: o segundo save precisa ter sido gravado.');
    }

    function testPublishingAnOpportunityWithoutAFederativeSealEnqueuesNothing()
    {
        $opportunity = $this->completeOpportunity(Opportunity::STATUS_DRAFT);
        $this->purgeSendJobs();

        $opportunity->status = Opportunity::STATUS_ENABLED;
        $opportunity->save(true);

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame(Opportunity::STATUS_ENABLED, (int) $reloaded->status, 'Premissa do teste: sem publicar, o teste passaria pelo motivo errado.');
        $this->assertNull($this->resolveFederativeEntity($opportunity), 'Premissa do teste: esta oportunidade não pode ter selo de Ente Federado.');
        $this->assertSame([], $this->enqueuedSendJobs(), 'Oportunidade sem selo de Ente Federado não é edital do CultBR.');
    }

    // a fase é completa e selada de propósito: sem isso o teste passaria sem tocar o guard de fase
    function testNeitherSealingNorSavingACompleteSealedPhaseEnqueues()
    {
        $rootId = $this->sealedOpportunity(Opportunity::STATUS_ENABLED)->id;
        $sealId = $this->federativeSeal()->id;
        $phase = $this->completeOpportunity(Opportunity::STATUS_ENABLED);

        // a fixture da fase limpa o EntityManager: raiz e selo têm que voltar na mesma unidade de trabalho
        $phase->parent = $this->app->repo(Opportunity::class)->find($rootId);
        $phase->save(true);
        $this->purgeSendJobs();

        $phase->createSealRelation($this->app->repo(Seal::class)->find($sealId));
        $this->assertSame([], $this->enqueuedSendJobs(), 'Selar a fase não enfileira: quem vai ao CultBR é o edital raiz.');

        $phase->save(true);

        $reloadedPhase = $this->reloaded($phase);
        $this->assertSame($rootId, $reloadedPhase->parent?->id, 'Premissa do teste: o vínculo de fase precisa ter sido gravado.');
        $this->assertNotSame([], $reloadedPhase->getSealRelations(), 'Premissa do teste: a fase precisa carregar o selo, ou nada se prova sobre o guard de fase.');
        $this->assertSame([], $this->enqueuedSendJobs(), 'Salvar a fase selada também não enfileira, nem sendo ela completa.');
    }

    // o ciclo da fila, não o _execute à mão: é ele que roda sob o usuário do job e limpa a linha
    function testTheEnqueuedSendRunsThroughTheRealQueueCycle()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);

        // antes de qualquer reloaded(): o em->clear() dele desanexa o usuário e a gravação falharia
        $this->writeRawMetadata($opportunity, CultBrMetadata::SEND_REASON, 'recusa anterior');

        $token = $this->resolveFederativeEntity($opportunity)->token;
        $opportunity = $this->reloaded($opportunity);
        $this->assertNotEmpty($token, 'Premissa do teste: comparar com token vazio não provaria nada.');
        $this->assertSame('recusa anterior', $opportunity->getMetadata(CultBrMetadata::SEND_REASON), 'Premissa do teste: sem motivo gravado, o assertNull final não provaria limpeza.');
        $this->assertCount(1, $this->enqueuedSendJobs(), 'Premissa do teste: sem job na fila o ciclo não exercitaria nada.');

        Plugin::instance()->mode = Plugin::MODE_LIVE;
        Plugin::instance()->transport = $transport = FakeTransport::replying(200, ['id_par_edital' => 1]);
        $this->leaveOnlySendJobsQueued();

        $this->processJobs();

        $reloaded = $this->reloaded($opportunity);
        $this->assertSame(SendOutcome::SUCCESS, $reloaded->getMetadata(CultBrMetadata::SEND_STATUS), 'O desfecho precisa ser gravado pelo worker, sob o usuário do job, não só pelo job chamado à mão.');
        $this->assertNotNull($reloaded->getMetadata(CultBrMetadata::SEND_AT), 'Sem a data não há como saber quando o edital foi ao CultBR.');
        $this->assertNull($reloaded->getMetadata(CultBrMetadata::SEND_REASON), 'Envio aceito precisa apagar o motivo da recusa anterior, que é metadado público.');

        $this->assertCount(1, $transport->requestedUrls, 'O ciclo precisa ter chamado a API uma vez, nem zero nem duas.');
        $this->assertStringEndsWith("/api/v1/oportunidades/{$opportunity->id}", $transport->requestedUrls[0], 'O PUT vai para o id da própria oportunidade.');
        $this->assertSame($token, $transport->sentHeaders[0]['token'] ?? null, 'O worker precisa enviar com o token do Ente Federado, não sem credencial.');
        $this->assertArrayHasKey('numero_e_titulo_edital', $transport->sentBodies[0], 'Sem conferir o corpo, trocar o payload por array vazio passaria despercebido.');
        $this->assertSame($opportunity->id, $transport->sentBodies[0]['id'] ?? null, 'O corpo precisa descrever a oportunidade enviada, não outra.');

        $this->assertSame([], $this->enqueuedSendJobs(), 'Job executado sai da fila; se ficar, o edital é reenviado para sempre.');
    }

    // os jobs do PAR disputam a vez com o envio: consomem o transporte falso e corrompem o cache da árvore
    private function leaveOnlySendJobsQueued(): void
    {
        $this->app->em->getConnection()->executeStatement('DELETE FROM job WHERE name <> ?', [SendOpportunityJob::SLUG]);
    }

    /** @return Job[] */
    private function enqueuedSendJobs(): array
    {
        return $this->app->repo(Job::class)->findBy(['type' => SendOpportunityJob::SLUG]);
    }

    private function purgeSendJobs(): void
    {
        $this->app->em->getConnection()->delete('job', ['name' => SendOpportunityJob::SLUG]);
    }
}
