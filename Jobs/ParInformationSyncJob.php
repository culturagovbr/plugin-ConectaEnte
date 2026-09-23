<?php

namespace ConectaEnte\Jobs;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ParInformationService;
use MapasCulturais\App;
use MapasCulturais\Definitions\JobType;
use MapasCulturais\Entities\Job;

/**
 * Atualiza o cache da árvore do PAR de cada ente federado ativo, fora do
 * caminho da requisição do usuário. `_generateId` fixo faz `enqueueJob`
 * virar uma leitura por PK (não uma reescrita) quando já agendado — por
 * isso `Plugin::_init()` pode chamá-lo sem condição a cada boot.
 */
class ParInformationSyncJob extends JobType
{
    const SLUG = 'conectaente-par-information-sync';
    const INTERVAL = '+30 minutes';
    // ~5 anos de execuções a cada 30min: não há suporte a "para sempre" no core, então usa
    // um número grande em vez de reagendar manualmente a cada execução.
    const ITERATIONS = 87600;

    protected function _generateId(array $data, string $start_string, string $interval_string, int $iterations)
    {
        return self::SLUG;
    }

    public function _execute(Job $job)
    {
        $app = App::i();
        $service = Plugin::instance()->parInformationService();
        $client = Plugin::instance()->client();

        $federativeEntities = $app->repo(FederativeEntity::class)->findBy(['status' => FederativeEntity::STATUS_ENABLED]);

        foreach ($federativeEntities as $federativeEntity) {
            $result = $client->getParInformation($federativeEntity->token, $federativeEntity->document);

            // só o desfecho de sucesso (ou "não existe aqui") vale a pena guardar;
            // falha de rede ou token rejeitado não pode grudar no cache e mascarar uma correção.
            if ($result->tree || $result->notFound) {
                $app->cache->save(ParInformationService::cacheKey($federativeEntity), $result, $service->cacheTtl());
            } else {
                $app->log->warning("ParInformationSyncJob: falha ao atualizar o ente {$federativeEntity->id}: {$result->message}");
            }
        }

        return true;
    }
}
