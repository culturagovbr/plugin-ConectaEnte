<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Jobs\SendOpportunityJob;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Seal;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * Os gatilhos que enfileiram o envio do edital ao CultBR.
 */
class SendTriggersTest extends TestCase
{
    use PublicationRequirementsFixtures;

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

    // a fase é completa e selada de propósito: incompleta ou sem selo, ela não enfileiraria de
    // qualquer jeito, e o teste passaria sem exercitar o guard de fase
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
