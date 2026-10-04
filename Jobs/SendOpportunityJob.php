<?php

namespace ConectaEnte\Jobs;

use ConectaEnte\Plugin;
use ConectaEnte\Services\SendOutcome;
use MapasCulturais\App;
use MapasCulturais\Definitions\JobType;
use MapasCulturais\Entities\Job;
use MapasCulturais\Entities\Opportunity;

/**
 * Envia o edital selado ao CultBR, com retentativa só para indisponibilidade (5xx/conexão).
 */
class SendOpportunityJob extends JobType
{
    // a coluna do slug no core tem 32 caracteres: nome mais longo não salva
    const SLUG = 'conectaente-send-opportunity';

    static function dataFor(Opportunity $opportunity, int $attempt = 1): array
    {
        return ['opportunityId' => $opportunity->id, 'attempt' => $attempt];
    }

    // dedupe só pelo id da oportunidade: um novo save, ou a própria retentativa, substitui
    // o que estava na fila — do mesmo jeito que ParInformationFetchJob dedupe por ente
    protected function _generateId(array $data, string $start_string, string $interval_string, int $iterations)
    {
        return (string) $data['opportunityId'];
    }

    public function _execute(Job $job)
    {
        $app = App::i();
        $opportunity = $app->repo(Opportunity::class)->find($job->opportunityId);

        // entre enfileirar e executar a oportunidade pode ter ido para a lixeira, ou deixado de ser elegível
        if (!$opportunity || !Plugin::instance()->sendEligibility()->isEligible($opportunity)) {
            return true;
        }

        $attempt = (int) ($job->attempt ?? 1);

        try {
            $outcome = Plugin::instance()->opportunitySender()->send($opportunity);

            if ($outcome->isRetryable()) {
                $this->retryOrGiveUp($opportunity, $attempt, $outcome);
            }
        } catch (\Throwable $error) {
            $this->recordFailure($opportunity, $error);
        }

        return true;
    }

    // nem o registro pode escapar: _execute que lança prende a linha do job em processamento para sempre
    private function recordFailure(Opportunity $opportunity, \Throwable $error): void
    {
        $app = App::i();
        $app->log->error("SendOpportunityJob: exceção ao enviar a oportunidade {$opportunity->id}: {$error->getMessage()}");

        try {
            Plugin::instance()->opportunitySender()->recordFailure($opportunity);
        } catch (\Throwable $failure) {
            $app->log->error("SendOpportunityJob: falha ao registrar o desfecho da oportunidade {$opportunity->id}: {$failure->getMessage()}");
        }
    }

    private function retryOrGiveUp(Opportunity $opportunity, int $attempt, SendOutcome $lastAttempt): void
    {
        $plugin = Plugin::instance();

        if ($attempt >= $plugin->sendMaxAttempts()) {
            $plugin->opportunitySender()->recordExhausted($opportunity, $lastAttempt);

            return;
        }

        $plugin->scheduleSend($opportunity, $attempt + 1, "+{$plugin->sendRetryDelaySeconds()} seconds");
    }
}
