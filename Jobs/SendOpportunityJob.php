<?php

namespace ConectaEnte\Jobs;

use ConectaEnte\Plugin;
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
                $this->retryOrGiveUp($opportunity, $attempt);
            }
        } catch (\Throwable $e) {
            // _execute nunca pode lançar: o core prende a linha em status=1 (processando) para sempre
            $app->log->error("SendOpportunityJob: exceção ao enviar a oportunidade {$opportunity->id}: {$e->getMessage()}");
        }

        return true;
    }

    private function retryOrGiveUp(Opportunity $opportunity, int $attempt): void
    {
        $plugin = Plugin::instance();

        if ($attempt >= $plugin->sendMaxAttempts()) {
            $plugin->opportunitySender()->recordExhausted($opportunity);

            return;
        }

        $plugin->scheduleSend($opportunity, $attempt + 1, "+{$plugin->sendRetryDelaySeconds()} seconds");
    }
}
