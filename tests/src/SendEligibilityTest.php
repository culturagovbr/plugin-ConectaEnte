<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * Quais oportunidades estão aptas a ir ao CultBR, e por que as outras não vão.
 */
class SendEligibilityTest extends TestCase
{
    use PublicationRequirementsFixtures;

    function testWithoutAnySealIsNotEligible()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunity(Opportunity::STATUS_ENABLED);

        $this->assertNotEligible($opportunity);
    }

    function testWithASealThatIsNotAFederativeEntityIsNotEligible()
    {
        $this->loginAsSaasSuperAdmin();
        $opportunity = $this->createOpportunityWithSeal($this->createSeal(), Opportunity::STATUS_ENABLED);

        $this->assertNotEligible($opportunity);
    }

    function testDraftIsNotEligibleEvenComplete()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);

        $this->assertNotEligible($opportunity);
    }

    function testCompletePublishedIsEligible()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);

        $this->assertTrue(Plugin::instance()->sendEligibility()->isEligible($this->reloaded($opportunity)));
    }

    function testArchivedIsNotEligible()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->app->disableAccessControl();
        $opportunity->status = Opportunity::STATUS_ARCHIVED;
        $opportunity->save(true);
        $this->app->enableAccessControl();

        $this->assertNotEligible($opportunity);
    }

    function testTrashedIsNotEligible()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $this->app->disableAccessControl();
        $opportunity->delete(true);
        $this->app->enableAccessControl();

        $this->assertNotEligible($opportunity);
    }

    function testPublishedIncompleteIsNotEligibleAndNamesWhatIsMissing()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);

        $reason = Plugin::instance()->sendEligibility()->ineligibilityReason($this->reloaded($opportunity));

        $this->assertNotNull($reason);
        $this->assertStringContainsString('campo', mb_strtolower($reason));
        $this->assertStringNotContainsString('conectaente_', $reason, 'O motivo vai para o log e para quem procura o campo na tela: ali ele tem rótulo, não chave de metadado.');
        $this->assertStringContainsString('Tipo de Edital', $reason, 'A fixture incompleta é justamente a que não tem esse campo, e é o rótulo dele que o gestor procura.');
    }

    // a chave do e-mail é sintética e não é metadado registrado: era por onde o prefixo escapava
    function testTheSyntheticEmailKeyAlsoBecomesALabel()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => [['tipo' => 'email', 'descricao' => 'secretaria de cultura']]];
        $opportunity->save(true);

        $reason = Plugin::instance()->sendEligibility()->ineligibilityReason($this->reloaded($opportunity));

        $this->assertNotNull($reason, 'Premissa do teste: o e-mail inválido precisa tornar a oportunidade inelegível.');
        $this->assertStringNotContainsString('conectaente_', $reason, 'Nenhuma chave escapa como nome de campo, nem a que não é metadado registrado.');
    }

    function testPhaseIsNeverEligible()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $phase = $this->reloaded($opportunity)->lastPhase;

        $this->assertNotEligible($phase);
    }

    // o terceiro motivo (campos faltando) já é travado em testPublishedIncompleteIsNotEligibleAndNamesWhatIsMissing
    function testTheReasonForNoSealDiffersFromTheReasonForDraft()
    {
        $withoutSeal = $this->completeOpportunity(Opportunity::STATUS_ENABLED);
        $draft = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);

        $eligibility = Plugin::instance()->sendEligibility();
        $withoutSealReason = (string) $eligibility->ineligibilityReason($this->reloaded($withoutSeal));
        $draftReason = (string) $eligibility->ineligibilityReason($this->reloaded($draft));

        $this->assertStringContainsString('selo', mb_strtolower($withoutSealReason));
        $this->assertStringContainsString('publicada', mb_strtolower($draftReason));
        $this->assertNotSame($withoutSealReason, $draftReason, 'Sem selo e em rascunho pedem condutas diferentes do gestor.');
    }

    // o motivo so dizia "Faltam N campo(s)": a razao que cada validacao escreveu era descartada,
    // e campo preenchido com valor invalido aparecia como campo faltando
    function testTheReasonKeepsWhatEachValidationSaid()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $opportunity->{CultBrMetadata::PAR_EXERCISE_ID} = '700.9';
        $opportunity->save(true);

        $reason = (string) Plugin::instance()->sendEligibility()->ineligibilityReason($this->reloaded($opportunity));

        $this->assertStringContainsString('não guarda um id do PAR', $reason, 'A razão escrita pela validação tem que chegar ao gestor.');
        $this->assertStringContainsString('Exercício do PAR', $reason, 'E tem que nomear o campo em que o valor está errado.');
        $this->assertStringNotContainsString('Faltam', $reason, 'O campo está preenchido: dizer que falta manda o gestor procurar a coisa errada.');
    }

    // quarenta mensagens do gate ja nomeiam o campo; prefixar todas daria "X: O campo X e obrigatorio"
    function testTheLabelIsNotRepeatedWhenTheMessageAlreadyNamesTheField()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $opportunity->{CultBrMetadata::PAR_EXERCISE_ID} = '700.9';
        $opportunity->save(true);

        $reason = (string) Plugin::instance()->sendEligibility()->ineligibilityReason($this->reloaded($opportunity));

        $this->assertSame(1, substr_count($reason, 'Exercício do PAR'), "O rótulo aparece uma vez: {$reason}");
    }

    // seis mensagens do gate nao nomeiam o campo, entre elas a do e-mail: para essas o rotulo e prefixado
    function testAReasonThatDoesNotNameItsFieldGetsTheLabelPrefixed()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => [['tipo' => 'email', 'descricao' => 'secretaria de cultura']]];
        $opportunity->save(true);

        $reason = (string) Plugin::instance()->sendEligibility()->ineligibilityReason($this->reloaded($opportunity));

        $this->assertStringContainsString('Informe um e-mail válido', $reason, 'A razão específica aparece.');
        $this->assertMatchesRegularExpression('/\S+.*: Informe um e-mail válido/', $reason, 'E vem precedida do campo, porque a frase sozinha não diz onde corrigir.');
    }

    function testEveryFailedValidationAppearsInTheReason()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $opportunity->{CultBrMetadata::PAR_EXERCISE_ID} = '700.9';
        $opportunity->conectaente_executionType = null;
        $opportunity->save(true);

        $reason = (string) Plugin::instance()->sendEligibility()->ineligibilityReason($this->reloaded($opportunity));

        $this->assertStringContainsString('id do PAR', $reason, 'Com duas validações reprovadas, a primeira aparece.');
        $this->assertStringContainsString('Tipo de Edital', $reason, 'E a segunda também: o gestor corrige as duas numa passada.');
    }

    private function assertNotEligible(Opportunity $opportunity): void
    {
        $reloaded = $this->reloaded($opportunity);
        $eligibility = Plugin::instance()->sendEligibility();

        $this->assertFalse($eligibility->isEligible($reloaded));
        $this->assertNotNull($eligibility->ineligibilityReason($reloaded));
    }
}
