<?php

namespace ConectaEnte\Jobs;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Plugin;
use MapasCulturais\App;
use MapasCulturais\Definitions\JobType;
use MapasCulturais\Entities\Job;

/**
 * Leva ao cache a árvore do PAR de um Ente Federado.
 */
class ParInformationFetchJob extends JobType
{
    // a coluna do slug no core tem 32 caracteres: nome mais longo não salva
    const SLUG = 'conectaente-par-fetch';

    static function dataFor(FederativeEntity $federativeEntity): array
    {
        return ['federativeEntityId' => $federativeEntity->id];
    }

    protected function _generateId(array $data, string $start_string, string $interval_string, int $iterations)
    {
        return (string) $data['federativeEntityId'];
    }

    public function _execute(Job $job)
    {
        $app = App::i();
        $federativeEntity = $app->repo(FederativeEntity::class)->find($job->federativeEntityId);

        // entre a seleção e a execução o ente pode ter ido para a lixeira
        if (!$federativeEntity || (int) $federativeEntity->status !== FederativeEntity::STATUS_ENABLED) {
            return true;
        }

        try {
            $result = Plugin::instance()->parInformationService()->fetch($federativeEntity);

            if (!$result->tree && !$result->notFound) {
                $app->log->warning("ParInformationFetchJob: falha ao atualizar o ente {$federativeEntity->id}: {$result->message}");
            }
        } catch (\Throwable $e) {
            $app->log->error("ParInformationFetchJob: exceção ao atualizar o ente {$federativeEntity->id}: {$e->getMessage()}");
        }

        return true;
    }
}
