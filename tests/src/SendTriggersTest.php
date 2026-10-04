<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Jobs\SendOpportunityJob;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;
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
