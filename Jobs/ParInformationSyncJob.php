<?php

namespace ConectaEnte\Jobs;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Plugin;
use ConectaEnte\Services\ParInformationService;
use MapasCulturais\App;
use MapasCulturais\Definitions\JobType;
use MapasCulturais\Entities\Job;

/**
 * Atualiza o cache da árvore do PAR de cada Ente Federado ativo, fora da requisição do usuário.
 */
class ParInformationSyncJob extends JobType
{
    const SLUG = 'conectaente-par-information-sync';
    const INTERVAL = '+30 minutes';
    // o core não tem "para sempre": um número grande evita reagendar a cada execução
    const ITERATIONS = 87600;

    protected function _generateId(array $data, string $start_string, string $interval_string, int $iterations)
    {
        return self::SLUG;
    }

    // true mesmo em falha: o core só reagenda a próxima execução quando `_execute` tem sucesso
    public function _execute(Job $job)
    {
        $app = App::i();

        try {
            $this->syncAll($app);
        } catch (\Throwable $e) {
            $app->log->error("ParInformationSyncJob falhou: {$e->getMessage()}");
        }

        return true;
    }

    private function syncAll(App $app): void
    {
        $service = Plugin::instance()->parInformationService();
        $client = Plugin::instance()->client();

        $federativeEntities = $app->repo(FederativeEntity::class)->findBy(['status' => FederativeEntity::STATUS_ENABLED]);

        foreach ($federativeEntities as $federativeEntity) {
            try {
                $result = $client->getParInformation($federativeEntity->token, $federativeEntity->document);

                // falha de rede ou token rejeitado não pode grudar no cache e mascarar a correção
                if ($result->tree || $result->notFound) {
                    $app->cache->save(ParInformationService::cacheKey($federativeEntity), $result, $service->cacheTtl());
                } else {
                    $app->log->warning("ParInformationSyncJob: falha ao atualizar o ente {$federativeEntity->id}: {$result->message}");
                }
            } catch (\Throwable $e) {
                // um ente com erro não pode interromper a sincronização dos demais
                $app->log->error("ParInformationSyncJob: exceção ao atualizar o ente {$federativeEntity->id}: {$e->getMessage()}");
            }
        }
    }
}
