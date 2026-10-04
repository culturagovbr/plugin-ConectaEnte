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

    /** @return Job[] */
    private function enqueuedSendJobs(): array
    {
        return $this->app->repo(Job::class)->findBy(['type' => SendOpportunityJob::SLUG]);
    }
}
