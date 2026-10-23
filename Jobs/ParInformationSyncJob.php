<?php

namespace ConectaEnte\Jobs;

use ConectaEnte\Entities\FederativeEntitySeal;
use MapasCulturais\App;
use MapasCulturais\Definitions\JobType;
use MapasCulturais\Entities\Job;

/**
 * Enfileira a busca da árvore do PAR de cada Ente Federado que alguém vai abrir, um job por ente.
 */
class ParInformationSyncJob extends JobType
{
    const SLUG = 'conectaente-par-information-sync';

    const FETCH_SPACING_SECONDS = 1;

    protected function _generateId(array $data, string $start_string, string $interval_string, int $iterations)
    {
        return self::SLUG;
    }

    public function _execute(Job $job)
    {
        $app = App::i();
        $federativeEntities = $app->repo(FederativeEntitySeal::class)->findEntitiesSealingLiveOpportunities();

        // um segundo entre as buscas: enfileiradas todas para agora, elas ficariam à frente de qualquer outro job
        foreach ($federativeEntities as $position => $federativeEntity) {
            $start = '+' . ($position * self::FETCH_SPACING_SECONDS) . ' seconds';

            $app->enqueueJob(ParInformationFetchJob::SLUG, ParInformationFetchJob::dataFor($federativeEntity), $start, '', 1);
        }

        return true;
    }
}
