<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use ConectaEnte\Services\ResultAnswers;
use ConectaEnte\Services\ResultForm;
use MapasCulturais\Entities\Agent;
use MapasCulturais\Entities\EvaluationMethodConfiguration;
use MapasCulturais\Entities\EvaluationMethodConfigurationAgentRelation as ValuerRelation;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\UserDirector;

/**
 * A comissão avaliadora do edital na questão 13933, com o CPF guardado no servidor.
 */
class ResultCommitteeTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use UserDirector;

    const COMMITTEE = 13933;
    const CPF = '529.982.247-25';

    function testTheQuestionOpensWithTheValuersOfTheEdital()
    {
        $opportunity = $this->editalWithPhase();
        $valuer = $this->valuer($opportunity, 'Mérito');

        $rows = $this->answers()->committee($this->reloaded($opportunity), $this->form())[self::COMMITTEE];

        $this->assertEquals([['agentId' => $valuer->id, 'nome' => $valuer->name, 'cpf' => '***.982.247-**', 'vinculo' => '', 'categoria_grupo' => 'Mérito']], $rows);
    }

    function testOnlyTheValuersInActivityAreBrought()
    {
        $opportunity = $this->editalWithPhase();
        $active = $this->valuer($opportunity);
        $sent = $this->valuer($opportunity, status: ValuerRelation::STATUS_SENT);
        $this->valuer($opportunity, status: ValuerRelation::STATUS_DISABLED);
        $this->valuer($opportunity, status: ValuerRelation::STATUS_PENDING);

        $rows = $this->answers()->committee($this->reloaded($opportunity), $this->form())[self::COMMITTEE];

        $this->assertEqualsCanonicalizing([$active->id, $sent->id], array_column($rows, 'agentId'));
    }

    function testTheValuerOfTwoGroupsIsOneRow()
    {
        $opportunity = $this->editalWithPhase();
        $valuer = $this->valuer($opportunity, 'Mérito');
        $this->relate($opportunity, $valuer, 'Habilitação');

        $rows = $this->answers()->committee($this->reloaded($opportunity), $this->form())[self::COMMITTEE];

        $this->assertCount(1, $rows);
        $this->assertStringContainsString('Mérito', $rows[0]['categoria_grupo']);
        $this->assertStringContainsString('Habilitação', $rows[0]['categoria_grupo']);
    }

    function testTheStoredAnswerKeepsTheAgentAndNotTheDocument()
    {
        $opportunity = $this->editalWithPhase();
        $valuer = $this->valuer($opportunity);

        $this->assertSame([], $this->saveCommittee($opportunity));

        $opportunity = $this->reloaded($opportunity);
        $stored = $this->answers()->saved($opportunity)['answers'][self::COMMITTEE];
        $this->assertSame($valuer->id, $stored[0]['agentId']);
        $this->assertStringNotContainsString('982', json_encode($stored));
        $this->assertSame('***.982.247-**', $this->answers()->forScreen($opportunity, $this->form())[self::COMMITTEE][0]['cpf']);
    }

    function testTheSendCarriesTheWholeDocumentAndNotTheAgent()
    {
        $opportunity = $this->editalWithPhase();
        $valuer = $this->valuer($opportunity, 'Mérito', cpf: '52998224725');
        $this->saveCommittee($opportunity);

        $row = $this->answers()->toSend($this->reloaded($opportunity), $this->form())[self::COMMITTEE][0];

        $this->assertEquals(['nome' => $valuer->name, 'cpf' => self::CPF, 'vinculo' => 'contratado', 'categoria_grupo' => 'Mérito'], $row);
    }

    function testTheDocumentIsFoundEvenWithoutControlOfTheValuer()
    {
        $opportunity = $this->editalWithPhase();
        $this->valuer($opportunity);
        $this->saveCommittee($opportunity);
        $stored = $this->answers()->saved($this->reloaded($opportunity))['answers'];
        $this->login($this->userDirector->createUser());
        $this->app->em->clear();

        $this->assertSame(self::CPF, Plugin::instance()->resultCommittee()->complete($stored, $this->form())[self::COMMITTEE][0]['cpf']);
    }

    function testAnAgentOutsideTheCommitteeIsNotResolved()
    {
        $opportunity = $this->editalWithPhase();
        $outsider = $this->agent();
        $row = ['agentId' => $outsider->id, 'nome' => 'Fulana', 'cpf' => '***.982.247-**', 'vinculo' => 'contratado', 'categoria_grupo' => 'Todas'];

        $errors = $this->answers()->save($this->reloaded($opportunity), $this->form(), [self::COMMITTEE => [$row]]);

        $this->assertStringContainsString('documento inválido', $errors[self::COMMITTEE]);
        $this->assertArrayNotHasKey(self::COMMITTEE, $this->answers()->saved($this->reloaded($opportunity))['answers']);
    }

    function testTheValuerWhoLeftTheCommitteeAfterTheAnswerStays()
    {
        $opportunity = $this->editalWithPhase();
        $valuer = $this->valuer($opportunity);
        $this->saveCommittee($opportunity);
        $this->phaseOf($opportunity)->removeAgentRelation($this->app->repo(Agent::class)->find($valuer->id), 'Comissão de avaliação');

        $opportunity = $this->reloaded($opportunity);
        $errors = $this->answers()->save($opportunity, $this->form(), $this->answers()->forScreen($opportunity, $this->form()));

        $this->assertSame([], $errors);
        $this->assertSame(self::CPF, $this->answers()->toSend($this->reloaded($opportunity), $this->form())[self::COMMITTEE][0]['cpf']);
    }

    function testTheValuerWithoutDocumentHasItTypedByTheManager()
    {
        $opportunity = $this->editalWithPhase();
        $this->valuer($opportunity, cpf: null);

        $this->assertSame('', $this->answers()->committee($this->reloaded($opportunity), $this->form())[self::COMMITTEE][0]['cpf']);
        $this->assertStringContainsString('CPF: preenchimento obrigatório', $this->saveCommittee($opportunity)[self::COMMITTEE]);
        $this->assertSame([], $this->saveCommittee($opportunity, ['cpf' => '111.444.777-35']));
        $this->assertSame('111.444.777-35', $this->answers()->toSend($this->reloaded($opportunity), $this->form())[self::COMMITTEE][0]['cpf']);
    }

    function testTheInvalidDocumentOfTheRegistryCountsAsNone()
    {
        $opportunity = $this->editalWithPhase();
        $this->valuer($opportunity, cpf: '123.456.789-00');

        $this->assertSame('', $this->answers()->committee($this->reloaded($opportunity), $this->form())[self::COMMITTEE][0]['cpf']);
    }

    function testTheAnsweredQuestionIsNotFilledAgain()
    {
        $opportunity = $this->editalWithPhase();
        $this->valuer($opportunity);
        $typed = ['nome' => 'Fulana de Tal', 'cpf' => self::CPF, 'vinculo' => 'voluntário', 'categoria_grupo' => 'Todas'];
        $this->answers()->save($this->reloaded($opportunity), $this->form(), [self::COMMITTEE => [$typed]]);

        $opportunity = $this->reloaded($opportunity);

        $this->assertSame([], $this->answers()->committee($opportunity, $this->form()));
        $this->assertEquals([$typed], $this->answers()->toSend($opportunity, $this->form())[self::COMMITTEE], 'A linha digitada vai como foi digitada.');
    }

    function testTheValuersOfTheReportingPhaseStayOut()
    {
        $opportunity = $this->editalWithPhase();
        $this->valuer($opportunity);
        $opportunity = $this->reloaded($opportunity);
        $opportunity->setMetadata('isReportingPhase', '1');
        $opportunity->saveMetadata();

        $this->assertSame([], $this->answers()->committee($this->reloaded($opportunity), $this->form()));
    }

    function testWithoutValuersNothingIsSuggested()
    {
        $this->assertSame([], $this->answers()->committee($this->editalWithPhase(), $this->form()));
    }

    function testTheAttachmentOfTheCommitteeIsNotATable()
    {
        $form = new ResultForm(['secoes' => [['etapas' => [['questoes' => [
            ['id_questao_etapa' => 1, 'tipo_questao' => 'anexo', 'tag' => 'planilha_pdf_de_comissao_avaliadora'],
            ['id_questao_etapa' => 2, 'tipo_questao' => 'Tabela Dinâmica', 'tag' => 'res31_comissao_avaliadora'],
        ]]]]]]);

        $this->assertSame([2], $form->committeeIds());
    }

    private function editalWithPhase(): Opportunity
    {
        $opportunity = $this->completeOpportunity();

        $phase = new EvaluationMethodConfiguration();
        $phase->opportunity = $opportunity;
        $phase->type = 'simple';
        $phase->name = 'Avaliação';
        $phase->evaluationFrom = new \DateTime('-2 days');
        $phase->evaluationTo = new \DateTime('+2 days');
        $phase->save(true);

        return $this->reloaded($opportunity);
    }

    private function phaseOf(Opportunity $opportunity): EvaluationMethodConfiguration
    {
        return $this->reloaded($opportunity)->evaluationMethodConfiguration;
    }

    private function agent(?string $cpf = self::CPF): Agent
    {
        $agent = $this->userDirector->createUser()->profile;
        $agent->cpf = (string) $cpf;
        $agent->save(true);

        return $agent;
    }

    private function valuer(Opportunity $opportunity, string $group = 'Comissão de avaliação', ?string $cpf = self::CPF, int $status = ValuerRelation::STATUS_ACTIVE): Agent
    {
        $agent = $this->agent($cpf);
        $this->relate($opportunity, $agent, $group, $status);

        return $agent;
    }

    private function relate(Opportunity $opportunity, Agent $agent, string $group, int $status = ValuerRelation::STATUS_ACTIVE): void
    {
        $phase = $this->phaseOf($opportunity);
        $relation = $phase->createAgentRelation($this->app->repo(Agent::class)->find($agent->id), $group, true);
        $this->app->em->getConnection()->update('agent_relation', ['status' => $status], ['id' => $relation->id]);
    }

    private function saveCommittee(Opportunity $opportunity, array $cells = []): array
    {
        $opportunity = $this->reloaded($opportunity);
        $rows = $this->answers()->committee($opportunity, $this->form())[self::COMMITTEE];
        $rows = array_map(fn($row) => $cells + ['vinculo' => 'contratado'] + $row, $rows);

        return $this->answers()->save($opportunity, $this->form(), [self::COMMITTEE => $rows]);
    }

    private function form(): ResultForm
    {
        return new ResultForm(json_decode((string) file_get_contents(Plugin::instance()->fixturesPath() . '/estrutura.json'), true));
    }

    private function answers(): ResultAnswers
    {
        return Plugin::instance()->resultAnswers();
    }
}
