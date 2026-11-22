<?php

namespace ConectaEnte\Jobs;

use ConectaEnte\Entities\Log;
use ConectaEnte\Plugin;
use MapasCulturais\App;
use MapasCulturais\Definitions\JobType;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;

/**
 * Envia o resultado do edital ao CultBR, uma vez, sem retentativa.
 */
class SendResultJob extends JobType
{
    // a coluna do slug no core tem 32 caracteres: nome mais longo não salva
    const SLUG = 'conectaente-send-result';

    static function dataFor(Opportunity $opportunity, Log $log): array
    {
        return ['opportunityId' => $opportunity->id, 'logUuid' => $log->uuid];
    }

    // um por edital; quem segura o segundo clique é a trava do envelope, não este id
    protected function _generateId(array $data, string $start_string, string $interval_string, int $iterations)
    {
        return 'result-' . $data['opportunityId'];
    }

    public function _execute(Job $job)
    {
        $app = App::i();

        try {
            $opportunity = $app->repo(Opportunity::class)->find($job->opportunityId);
            $log = $app->repo(Log::class)->findOneBy(['uuid' => $job->logUuid]);

            if ($opportunity && $log) {
                Plugin::instance()->resultSender()->send($opportunity, $log);
            }
        } catch (\Throwable $error) {
            $app->log->error("SendResultJob: exceção no envio do resultado da oportunidade {$job->opportunityId}: {$error->getMessage()}");
        }

        return true;
    }
}
