<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Controllers\ConectaEnteController;
use ConectaEnte\Metadata\CultBrMetadata;
use MapasCulturais\Entities\Opportunity;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

class OpportunityRequirementsRouteTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    function testGuestIsAskedToLogIn()
    {
        $this->assertStatus401($this->requestFactory->GET('conectaente', 'opportunityRequirements', [1]));
    }

    function testUserWhoCannotEditTheOpportunityIsRefused()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);
        $this->login($this->userDirector->createUser());

        $this->assertSame(403, $this->send($this->requirements($opportunity->id)));
    }

    function testMissingOpportunityIsNotFound()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertSame(404, $this->send($this->requirements(999999)));
    }

    function testUnsealedOpportunityHasNothingToReport()
    {
        $opportunity = $this->coreCompleteOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);
        $opportunity->shortDescription = '';
        $opportunity->save(true);

        $this->assertSame(200, $this->send($this->requirements($opportunity->id)));

        $this->assertSame(['sealed' => false, 'missing' => [], 'labels' => [], 'anchors' => [], 'groups' => []], $this->responseJson());
    }

    function testSealedIncompleteOpportunityListsThePluginAndTheCoreFields()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);
        $opportunity->shortDescription = '';
        $opportunity->vacancies = 0;
        $opportunity->save(true);

        $this->assertSame(200, $this->send($this->requirements($opportunity->id)));

        $response = $this->responseJson();
        $this->assertTrue($response['sealed']);
        $this->assertEqualsCanonicalizing(['shortDescription', 'vacancies', 'conectaente_executionType'], array_keys($response['missing']));
        $this->assertEquals([
            'shortDescription' => 'Descrição Curta',
            'vacancies' => 'Total de vagas',
            'conectaente_executionType' => 'Tipo de Edital',
        ], $response['labels']);
    }

    function testSealedCompleteOpportunityHasNothingMissing()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);

        $this->assertSame(200, $this->send($this->requirements($opportunity->id)));

        $this->assertSame(['sealed' => true, 'missing' => [], 'labels' => [], 'anchors' => [], 'groups' => []], $this->responseJson());
    }

    function testRouteAnswersWhatThePublicationRefuses()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);

        $this->send($this->requirements($opportunity->id));
        $missing = $this->responseJson()['missing'];
        $this->assertSame(400, $this->send($this->requestFactory->POST('opportunity', 'publish', [$opportunity->id])));

        $this->assertSame(array_keys($this->responseJson()['data']), array_keys($missing), 'As chaves são as mesmas; a rota só omite a mensagem que repete o rótulo.');
    }

    function testFieldsWithoutARegisteredLabelGetTheCoreScreenText()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->getFile('rules')->delete(true);
        $opportunity->terms = ['area' => []];
        $opportunity->registrationProponentTypes = [];
        $opportunity->registrationRanges = [['label' => 'Faixa única', 'limit' => 1, 'value' => 1]];
        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => [['tipo' => 'email', 'descricao' => 'secretaria']]];
        $opportunity->save(true);

        $this->send($this->requirements($opportunity->id));

        $this->assertEquals([
            'rules' => 'Regulamento',
            'term-area' => 'Área de Interesse',
            'registrationProponentTypes' => 'Tipos do proponente',
            'registrationRangesVacancies' => 'Faixas/linhas',
            'registrationRangesTotalResource' => 'Faixas/linhas',
            'conectaente_registrationChannelsEmail' => 'Formas de inscrição previstas no edital',
        ], $this->responseJson()['labels']);
    }

    function testKeyWithoutAnyLabelIsShownAsItself()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $isActive = true;
        // desligado no fim, porque hook não sai da App
        $this->app->hook('entity(Opportunity).validationErrors', function (&$errors) use (&$isActive) {
            if ($isActive) {
                $errors['themeField'] = ['Campo exigido pelo tema.'];
            }
        });

        try {
            $this->send($this->requirements($opportunity->id));
        } finally {
            $isActive = false;
        }

        $this->assertSame(['themeField' => 'themeField'], $this->responseJson()['labels']);
    }

    function testMessageThatOnlyRepeatsTheLabelIsLeftOut()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->{CultBrMetadata::EXECUTION_TYPE} = null;
        $opportunity->registrationProponentTypes = [];
        $opportunity->save(true);

        $this->send($this->requirements($opportunity->id));

        $missing = $this->responseJson()['missing'];

        $this->assertSame([], $missing[CultBrMetadata::EXECUTION_TYPE], 'A chave continua pendente; a tela mostra só o rótulo.');
        $this->assertSame([], $missing['registrationProponentTypes']);
    }

    function testMessageThatSaysMoreThanRequiredStays()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->vacancies = 0;
        $opportunity->{CultBrMetadata::QUOTA_RESERVATION} = [];
        $opportunity->save(true);

        $this->send($this->requirements($opportunity->id));

        $missing = $this->responseJson()['missing'];

        $this->assertNotEmpty($missing['vacancies'], 'A mensagem diz que zero não vale, o que o rótulo não diz.');
        $this->assertNotEmpty($missing[CultBrMetadata::QUOTA_RESERVATION]);
    }

    function testPendencyWithoutAFieldOfItsOwnPointsToTheFieldThatProducedIt()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->registrationRanges = [['label' => 'Faixa única', 'limit' => 1, 'value' => 1]];
        $opportunity->conectaente_registrationChannels = ['previstasNoEdital' => 'sim', 'formas' => [['tipo' => 'email', 'descricao' => 'secretaria']]];
        $opportunity->save(true);

        $this->send($this->requirements($opportunity->id));

        $anchors = $this->responseJson()['anchors'];

        $this->assertSame('registrationRanges', $anchors['registrationRangesVacancies']);
        $this->assertSame('registrationRanges', $anchors['registrationRangesTotalResource']);
        $this->assertSame(CultBrMetadata::REGISTRATION_CHANNELS, $anchors['conectaente_registrationChannelsEmail']);
    }

    function testEveryOtherPendencyIsItsOwnAnchor()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->vacancies = null;
        $opportunity->{CultBrMetadata::SEGMENTS} = [];
        $opportunity->save(true);

        $this->send($this->requirements($opportunity->id));

        $json = $this->responseJson();

        $this->assertSame('vacancies', $json['anchors']['vacancies']);
        $this->assertSame(CultBrMetadata::SEGMENTS, $json['anchors'][CultBrMetadata::SEGMENTS]);
        $this->assertSame(array_keys($json['missing']), array_keys($json['anchors']), 'Toda pendência tem âncora, para a tela não decidir nada.');
    }

    function testEachPendencyComesWithItsOrigin()
    {
        // vacancies fica preenchido: a regra de faixas só compara a soma quando ele existe
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->registrationProponentTypes = [];
        $opportunity->registrationRanges = [['label' => 'Faixa única', 'limit' => 1, 'value' => 1]];
        $opportunity->{CultBrMetadata::SEGMENTS} = [];
        $opportunity->{CultBrMetadata::REGISTRATION_CHANNELS} = ['previstasNoEdital' => 'sim', 'formas' => [['tipo' => 'email', 'descricao' => 'secretaria']]];
        $opportunity->save(true);

        $this->send($this->requirements($opportunity->id));

        $groups = $this->responseJson()['groups'];

        $this->assertSame('core', $groups['registrationProponentTypes']);
        $this->assertSame('core', $groups['registrationRangesVacancies'], 'Pseudo-chave de faixa nasce de um campo do core.');
        $this->assertSame('plugin', $groups[CultBrMetadata::SEGMENTS]);
        $this->assertSame('plugin', $groups['conectaente_registrationChannelsEmail'], 'Pseudo-chave do canal nasce de um campo do plugin.');
    }

    function testEveryPendencyHasAnOrigin()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);

        $this->send($this->requirements($opportunity->id));

        $json = $this->responseJson();

        $this->assertSame(array_keys($json['missing']), array_keys($json['groups']), 'A tela agrupa pelo que o servidor diz, sem decidir nada.');
        $this->assertEmpty(array_diff($json['groups'], ['core', 'plugin']), 'Só existem essas duas origens.');
    }

    function testPendenciesComeInTheOrderTheTabReadsThem()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);
        // a data final vazia derruba também a inicial, e assim as duas datas entram na lista
        $opportunity->registrationTo = null;
        $opportunity->registrationProponentTypes = [];
        $opportunity->save(true);

        $this->send($this->requirements($opportunity->id));

        $keys = array_keys($this->responseJson()['missing']);
        $expected = array_values(array_intersect(ConectaEnteController::SCREEN_ORDER, $keys));

        $this->assertSame($expected, array_values(array_intersect($keys, $expected)), 'A lista é lida de cima para baixo, como o formulário.');
        $this->assertLessThan(
            array_search('registrationProponentTypes', $keys, true),
            array_search('registrationFrom', $keys, true),
            'As datas vêm antes dos tipos de proponente, como no CultEditais.',
        );
    }

    private function requirements(int $opportunityId): ServerRequestInterface
    {
        return $this->requestFactory->GET('conectaente', 'opportunityRequirements', [$opportunityId], ajax: true);
    }

    private function responseJson(): array
    {
        return json_decode((string) $this->app->response->getBody(), true);
    }
}
