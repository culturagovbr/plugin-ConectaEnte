<?php

namespace Tests\ConectaEnte\Traits;

use ConectaEnte\Jobs\SendOpportunityJob;
use MapasCulturais\Entities\Job;

trait SendQueue
{
    /** @return Job[] */
    protected function enqueuedSendJobs(): array
    {
        return $this->app->repo(Job::class)->findBy(['type' => SendOpportunityJob::SLUG]);
    }

    protected function purgeSendJobs(): void
    {
        $this->app->em->getConnection()->delete('job', ['name' => SendOpportunityJob::SLUG]);
    }

    /** Põe a linha no estado em que o cron a entrega a um worker. */
    protected function markAsRunning(Job $job): void
    {
        $job->status = Job::STATUS_PROCESSING;
        $this->app->em->flush();
    }

    /** O worker morreu com a linha na mão: o core só a recolhe depois de cinco minutos. */
    protected function markAsStuck(Job $job): void
    {
        $job->createTimestamp = new \DateTime('-6 minutes');
        $this->markAsRunning($job);
    }
}
