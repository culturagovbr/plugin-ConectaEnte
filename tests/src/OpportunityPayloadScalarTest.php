<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Payload\OpportunityPayload;
use ConectaEnte\Plugin;
use ConectaEnte\Vocabulary\ExecutionType;
use ConectaEnte\Vocabulary\LegalEntityType;
use ConectaEnte\Vocabulary\ProponentType;
use DateTime;
use MapasCulturais\Entities\Opportunity;
use RuntimeException;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * Os campos escalares do payload: cada um no seu tipo, e dado que falta saindo nulo.
 */
class OpportunityPayloadScalarTest extends TestCase
{
    use PublicationRequirementsFixtures;

    function testIdentificationComesFromTheOpportunity()
    {
        $payload = $this->payloadOf($this->sealed());

        $this->assertSame($this->opportunity->id, $payload['id']);
        $this->assertSame($this->opportunity->name, $payload['numero_e_titulo_edital']);
    }

    function testExecutionTypeGoesAsTheFixedPortugueseText()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::EXECUTION_TYPE} = ExecutionType::CULTURAL_EXECUTION->value;

        $this->assertSame(ExecutionType::CULTURAL_EXECUTION->text(), $this->payloadOf($opportunity)['forma_de_execucao']);
    }

    function testStatusGoesAsIdAndName()
    {
        $payload = $this->payloadOf($this->sealed(Opportunity::STATUS_ENABLED));

        $this->assertSame(['id' => 1, 'nome' => 'Ativado'], $payload['status']);
    }

    function testDatesGoInRfc3339WithTheInstallationTimezone()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::PUBLISHED_AT} = new DateTime('2026-09-24 10:30:45');

        $payload = $this->payloadOf($opportunity);

        $this->assertMatchesRegularExpression(
            '/^2026-09-24T10:30:45[+-]\d{2}:\d{2}$/',
            $payload['data_publicacao_edital'],
            'O core grava a data sem fuso; o payload leva o fuso da instalação.',
        );
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $payload['data_inicial_prazo_inscricao']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $payload['data_final_prazo_inscricao']);
    }

    function testObjectDetailPrefersTheLongDescription()
    {
        $opportunity = $this->sealed();
        $opportunity->longDescription = 'Objeto detalhado do edital';
        $opportunity->shortDescription = 'Resumo';

        $this->assertSame('Objeto detalhado do edital', $this->payloadOf($opportunity)['detalhamento_objeto']);
    }

    function testObjectDetailFallsBackToTheShortDescription()
    {
        $opportunity = $this->sealed();
        $opportunity->longDescription = '';

        $this->assertSame($opportunity->shortDescription, $this->payloadOf($opportunity)['detalhamento_objeto']);
    }

    function testStatusOutsideTheVocabularyIsRefusedNamingIt()
    {
        $opportunity = $this->sealed();
        $opportunity->status = 99;

        $this->expectExceptionMessageMatches('/99/');

        Plugin::instance()->opportunityPayload()->build($opportunity);
    }

    function testObjectDetailKeepsTheCultEditaisBlindCut()
    {
        $opportunity = $this->sealed();
        $opportunity->longDescription = str_repeat('a', 595) . '<b>negrito</b>';

        $this->assertSame(
            str_repeat('a', 595) . '<b>ne',
            $this->payloadOf($opportunity)['detalhamento_objeto'],
            'O corte é o do CultEditais, cego a HTML: o comportamento fica fixado até o CultBR dizer o que espera.',
        );
    }

    function testObjectDetailIsCutAtTheLimit()
    {
        $opportunity = $this->sealed();
        $opportunity->longDescription = str_repeat('á', 700);

        $this->assertSame(OpportunityPayload::DETAIL_MAX_LENGTH, mb_strlen($this->payloadOf($opportunity)['detalhamento_objeto']));
    }

    function testAmountsGoAsIntegerAndDecimalString()
    {
        $opportunity = $this->sealed();
        $opportunity->vacancies = 10;
        $opportunity->totalResource = 1500.5;

        $payload = $this->payloadOf($opportunity);

        $this->assertSame(10, $payload['numero_previsto_vagas']);
        $this->assertSame('1500.50', $payload['valor_total_edital'], 'O contrato pede reais como string de duas casas.');
    }

    function testProponentTypesAreTheOnesTheCultEditaisTranslates()
    {
        $opportunity = $this->sealed();
        $opportunity->registrationProponentTypes = ['Pessoa Física', 'MEI', 'Coletivo'];

        $this->assertSame([
            ProponentType::INDIVIDUAL->value,
            ProponentType::MEI->value,
            ProponentType::COLLECTIVE->value,
        ], $this->payloadOf($opportunity)['tipos_proponentes']);
    }

    function testLegalEntityBecomesTheVariantsTheAdministratorChose()
    {
        $opportunity = $this->sealed();
        $opportunity->registrationProponentTypes = [ProponentType::LEGAL_ENTITY_LABEL];
        $opportunity->{CultBrMetadata::LEGAL_ENTITY_TYPES} = [LegalEntityType::FOR_PROFIT->value, LegalEntityType::NON_PROFIT->value];

        $this->assertSame([
            ProponentType::FOR_PROFIT_LEGAL_ENTITY->value,
            ProponentType::NON_PROFIT_LEGAL_ENTITY->value,
        ], $this->payloadOf($opportunity)['tipos_proponentes'], 'O CultEditais manda pessoa_juridica, que o enum do contrato não tem.');
    }

    function testUnknownProponentLabelIsLeftOut()
    {
        $opportunity = $this->sealed();
        $opportunity->registrationProponentTypes = ['Pessoa Física', 'Ponto de Cultura'];

        $this->assertSame([ProponentType::INDIVIDUAL->value], $this->payloadOf($opportunity)['tipos_proponentes']);
    }

    function testEmptyListsGoAsEmptyArrays()
    {
        $opportunity = $this->sealed();
        $opportunity->registrationRanges = [];

        $payload = $this->payloadOf($opportunity);

        $this->assertSame([], $payload['categorias_edital']);
        $this->assertSame([], $payload['links_da_pagina_pnab']);
    }

    function testFederativeEntityGoesAsDocumentAndName()
    {
        $opportunity = $this->sealed();
        $entity = Plugin::instance()->sealedOpportunity()->federativeEntityOf($opportunity);

        $this->assertSame(['cnpj' => $entity->document, 'nome' => $entity->name], $this->payloadOf($opportunity)['ente_federado']);
    }

    function testMissingDataGoesAsNullNeverZeroOrEmptyString()
    {
        $opportunity = $this->opportunityWithoutRules();
        $opportunity->createSealRelation($this->federativeSeal());
        $opportunity = $this->reloaded($opportunity);
        $opportunity->{CultBrMetadata::EXECUTION_TYPE} = null;
        $opportunity->{CultBrMetadata::PUBLISHED_AT} = null;
        $opportunity->longDescription = '';
        $opportunity->shortDescription = '';
        $opportunity->totalResource = null;

        $payload = $this->payloadOf($opportunity);

        foreach (['forma_de_execucao', 'data_publicacao_edital', 'detalhamento_objeto', 'valor_total_edital', 'pdf_edital'] as $field) {
            $this->assertNull($payload[$field], "Dado que falta sai nulo em {$field}, para o envio nomeá-lo.");
        }
    }

    function testUnsealedOpportunityIsRefused()
    {
        $this->loginAsSaasSuperAdmin();
        $this->expectException(RuntimeException::class);

        Plugin::instance()->opportunityPayload()->build($this->createOpportunity());
    }

    function testTheHookLetsAnotherStoryAddItsFields()
    {
        $isActive = true;
        // desligado no fim, porque hook não sai da App
        $this->app->hook('conectaente.opportunityPayload', function ($opportunity, &$payload) use (&$isActive) {
            if ($isActive) {
                $payload['campo_de_outra_historia'] = 42;
            }
        });

        try {
            $payload = $this->payloadOf($this->sealed());
        } finally {
            $isActive = false;
        }

        $this->assertSame(42, $payload['campo_de_outra_historia']);
    }

    function testParIdsComeFromTheMetadataAsIntegers()
    {
        $payload = $this->payloadOf($this->sealed());

        $this->assertSame(2024, $payload['id_exercicio'], 'O metadado guarda string; o contrato tipa integer.');
        $this->assertSame(7, $payload['id_meta']);
        $this->assertSame(70, $payload['id_acao']);
        $this->assertSame(700, $payload['id_atividade']);
    }

    function testMissingParIdsGoAsNull()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::PAR_ACTIVITY_ID} = '';
        $opportunity->{CultBrMetadata::PAR_ACTION_ID} = null;

        $payload = $this->payloadOf($opportunity);

        $this->assertNull($payload['id_atividade'], 'Dado que falta sai nulo, para o envio nomeá-lo.');
        $this->assertNull($payload['id_acao'], 'Edital selado antes de os campos existirem não tem o metadado gravado.');
    }

    function testParIdThatIsNotAWholeNumberGoesAsNull()
    {
        $opportunity = $this->sealed();
        $opportunity->{CultBrMetadata::PAR_ACTIVITY_ID} = '700.9';

        $this->assertNull($this->payloadOf($opportunity)['id_atividade'], 'Id truncado silenciosamente iria ao CultBR como se fosse a atividade escolhida.');
    }

    private ?Opportunity $opportunity = null;

    /** Oportunidade selada e completa para o core e para a regra, relida do banco. */
    private function sealed(int $status = Opportunity::STATUS_ENABLED): Opportunity
    {
        return $this->reloaded($this->sealedOpportunity($status));
    }

    private function payloadOf(Opportunity $opportunity): array
    {
        $this->opportunity = $opportunity;

        return Plugin::instance()->opportunityPayload()->build($opportunity);
    }
}
