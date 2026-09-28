<?php

namespace ConectaEnte\Jobs;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Plugin;
use MapasCulturais\App;
use MapasCulturais\Definitions\JobType;
use MapasCulturais\Entities\Job;

/**
 * Aquece o cache da árvore do PAR de cada Ente Federado ativo, para a tela raramente esperar a API.
 */
class ParInformationSyncJob extends JobType
{
    const SLUG = 'conectaente-par-information-sync';

    protected function _generateId(array $data, string $start_string, string $interval_string, int $iterations)
    {
        return self::SLUG;
    }

    public function _execute(Job $job)
    {
        $app = App::i();
        $service = Plugin::instance()->parInformationService();

        $federativeEntities = $app->repo(FederativeEntity::class)->findBy(['status' => FederativeEntity::STATUS_ENABLED]);

        foreach ($federativeEntities as $federativeEntity) {
            try {
                $result = $service->fetch($federativeEntity);

                if (!$result->tree && !$result->notFound) {
                    $app->log->warning("ParInformationSyncJob: falha ao atualizar o ente {$federativeEntity->id}: {$result->message}");
                }
            } catch (\Throwable $e) {
                // um ente com erro não pode interromper a sincronização dos demais
                $app->log->error("ParInformationSyncJob: exceção ao atualizar o ente {$federativeEntity->id}: {$e->getMessage()}");
            }
        }

        return true;
    }
}
