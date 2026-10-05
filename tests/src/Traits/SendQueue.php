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
}
