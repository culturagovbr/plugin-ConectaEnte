<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Payload\PayloadValidation;
use ConectaEnte\Plugin;
use MapasCulturais\Entities\Opportunity;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * A conferência do payload montado: o contrato exige as 30 chaves presentes e nenhuma nula.
 */
class PayloadValidationTest extends TestCase
{
    use PublicationRequirementsFixtures;

    function testACompletePayloadHasNothingToReport()
    {
        $opportunity = $this->reloaded($this->sealedOpportunity(Opportunity::STATUS_ENABLED));
        $payload = Plugin::instance()->opportunityPayload()->build($opportunity);

        $this->assertSame([], $this->errorsFor($opportunity, $payload), 'Edital elegível não pode ter chave pendente.');
    }

    public static function contractKeys(): array
    {
        $keys = [...array_keys(PayloadValidation::SOURCES), ...PayloadValidation::INTERNAL];

        return array_combine($keys, array_map(fn($key) => [$key], $keys));
    }

    #[DataProvider('contractKeys')]
    function testEveryContractKeyIsReportedWhenItComesNull(string $key)
    {
        $opportunity = $this->reloaded($this->sealedOpportunity(Opportunity::STATUS_ENABLED));
        $payload = Plugin::instance()->opportunityPayload()->build($opportunity);
        $payload[$key] = null;

        $errors = $this->errorsFor($opportunity, $payload);

        $this->assertArrayHasKey($key, $errors, "A chave {$key} é obrigatória no contrato e não pode sair nula em silêncio.");
        $this->assertStringNotContainsString('conectaente_', $errors[$key], 'O motivo nomeia o campo pelo rótulo de tela, não pela chave de metadado.');
    }

    #[DataProvider('contractKeys')]
    function testEveryContractKeyIsReportedWhenItIsMissing(string $key)
    {
        $opportunity = $this->reloaded($this->sealedOpportunity(Opportunity::STATUS_ENABLED));
        $payload = Plugin::instance()->opportunityPayload()->build($opportunity);
        unset($payload[$key]);

        $this->assertArrayHasKey($key, $this->errorsFor($opportunity, $payload), "Chave ausente é tão grave quanto nula: o contrato exige as 30.");
    }

    // decisao travada: array e objeto vazios sao resposta, nao ausencia — o contrato os aceita
    function testEmptyArrayAndEmptyObjectCountAsPresent()
    {
        $opportunity = $this->reloaded($this->sealedOpportunity(Opportunity::STATUS_ENABLED));
        $payload = Plugin::instance()->opportunityPayload()->build($opportunity);
        $payload['categorias_edital'] = [];
        $payload['links_da_pagina_pnab'] = [];
        $payload['recursos_outras_fontes'] = (object) [];

        $this->assertSame([], $this->errorsFor($opportunity, $payload), 'Lista vazia é a resposta de quem não tem faixas, não campo faltando.');
    }

    // decisao travada: nulo dentro dos campos opacos espera envio real conferido do outro lado
    function testNullInsideAnOpaqueFieldIsNotReported()
    {
        $opportunity = $this->reloaded($this->sealedOpportunity(Opportunity::STATUS_ENABLED));
        $payload = Plugin::instance()->opportunityPayload()->build($opportunity);
        $payload['recursos_outras_fontes'] = ['houve_utilizacao' => 'nao', 'recursos_proprios' => null];

        $this->assertSame([], $this->errorsFor($opportunity, $payload), 'O CultEditais manda null nos mesmos lugares e a API aceita; reprovar aqui barraria edital legítimo.');
    }

    // o hook roda depois do build: extensao que remove chave tem que ser acusada
    function testAKeyRemovedByAnExtensionIsReported()
    {
        $opportunity = $this->reloaded($this->sealedOpportunity(Opportunity::STATUS_ENABLED));
        // desligar por flag, nao por clearHooks(): o clear() do core nao invalida o _hookCache,
        // entao um hook ja aplicado continua sendo chamado depois de removido
        $removing = true;
        $this->app->hook('conectaente.opportunityPayload', function ($opportunity, &$payload) use (&$removing) {
            if ($removing) {
                unset($payload['numero_previsto_vagas']);
            }
        });

        try {
            $payload = Plugin::instance()->opportunityPayload()->build($opportunity);
        } finally {
            $removing = false;
        }

        $errors = $this->errorsFor($opportunity, $payload);

        $this->assertArrayHasKey('numero_previsto_vagas', $errors, 'Validar antes do hook deixaria passar o que a extensão tirou.');
        $this->assertStringContainsString('Total de vagas', $errors['numero_previsto_vagas']);
    }

    function testTheInternalKeysSayTheyAreNotAFieldToFill()
    {
        $opportunity = $this->reloaded($this->sealedOpportunity(Opportunity::STATUS_ENABLED));
        $payload = Plugin::instance()->opportunityPayload()->build($opportunity);
        $payload['id'] = null;

        $reason = $this->errorsFor($opportunity, $payload)['id'];

        $this->assertStringContainsString('inconsistência do plugin', $reason, 'O gestor não tem onde preencher o id: mandá-lo procurar o campo seria enganoso.');
    }

    function testTheThirtyContractKeysAreAllCovered()
    {
        $covered = [...array_keys(PayloadValidation::SOURCES), ...PayloadValidation::INTERNAL];
        $opportunity = $this->reloaded($this->sealedOpportunity(Opportunity::STATUS_ENABLED));

        $built = array_keys(Plugin::instance()->opportunityPayload()->build($opportunity));

        $this->assertSame([], array_diff($built, $covered), 'Chave montada e não conferida sairia nula sem ninguém notar.');
        $this->assertSame([], array_diff($covered, $built), 'Chave conferida e não montada seria reportada para sempre.');
        $this->assertCount(30, $covered, 'O ParEditalInMapas declara 30 propriedades, todas obrigatórias.');
    }

    // o SOURCES repete o que o build() expressa: se uma origem mudar la e nao aqui, o motivo
    // nomeia o campo errado. Isto nao pega a origem trocada, mas pega a que deixou de existir
    function testEverySourceResolvesToALabelTheManagerSeesOnScreen()
    {
        $opportunity = $this->reloaded($this->sealedOpportunity(Opportunity::STATUS_ENABLED));
        $labels = Plugin::instance()->fieldLabels();

        foreach (PayloadValidation::SOURCES as $contractKey => $source) {
            $label = $labels->of($opportunity, $source);

            $this->assertNotSame($source, $label, "A origem {$source} de {$contractKey} não tem rótulo: o motivo sairia com a chave crua.");
            $this->assertStringNotContainsString('conectaente_', $label, "A origem {$source} devolveu chave de metadado como rótulo.");
        }
    }

    private function errorsFor(Opportunity $opportunity, array $payload): array
    {
        return Plugin::instance()->payloadValidation()->errors($opportunity, $payload);
    }
}
