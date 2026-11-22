<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Jobs\SendOpportunityJob;
use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Doubles\FakeTransport;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\ConectaEnte\Traits\SendQueue;

/**
 * O tipo de ação e o Ente Federado de cada edital enviado, gravados no envio.
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
