<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Plugin;
use DateTime;
use MapasCulturais\Entities\Opportunity;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

class PublicationValidationTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    const MISSING_KEY = 'conectaente_executionType';

    function testPublishingIncompleteSealedOpportunityIsRefused()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);

        $this->assertSame(400, $this->send($this->requestFactory->POST('opportunity', 'publish', [$opportunity->id])));

        $this->assertSame([self::MISSING_KEY], array_keys($this->responseErrors()));
        $this->assertSame(Opportunity::STATUS_DRAFT, $this->reloaded($opportunity)->status);
    }

    function testPublishingCompleteSealedOpportunityPublishesAndStamps()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);

        $this->assertSame(200, $this->send($this->requestFactory->POST('opportunity', 'publish', [$opportunity->id])));

        $this->assertPublishedAndStamped($opportunity);
    }

    function testUnsealedOpportunityIsLeftToTheCore()
    {
        $opportunity = $this->coreCompleteOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);

        $this->assertSame(200, $this->send($this->requestFactory->POST('opportunity', 'publish', [$opportunity->id])));

        $this->assertSame(Opportunity::STATUS_ENABLED, $this->reloaded($opportunity)->status);
    }

    function testRegularOwnerIsRefusedLikeTheSaasSuperAdmin()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);
        $owner = $this->userDirector->createUser();
        $this->app->disableAccessControl();
        $opportunity->owner = $owner->profile;
        $opportunity->save(true);
        $this->app->enableAccessControl();
        $this->login($owner);

        $this->assertSame(400, $this->send($this->requestFactory->POST('opportunity', 'publish', [$opportunity->id])));

        $this->assertSame([self::MISSING_KEY], array_keys($this->responseErrors()));
    }

    function testCompleteSealedOpportunityPublishesThroughPatchAndPut()
    {
        $byPatch = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $this->assertSame(200, $this->send($this->requestFactory->PATCH_entity($byPatch, ['status' => Opportunity::STATUS_ENABLED])));
        $this->assertPublishedAndStamped($byPatch);

        $byPut = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $this->assertSame(200, $this->send($this->PUT($byPut, ['status' => Opportunity::STATUS_ENABLED])));
        $this->assertPublishedAndStamped($byPut);
    }

    function testPatchOfSealedDraftSaves()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);

        $this->assertSame(200, $this->send($this->requestFactory->PATCH_entity($opportunity, ['shortDescription' => 'Rascunho em andamento'])));

        $this->assertSame('Rascunho em andamento', $this->reloaded($opportunity)->shortDescription);
    }

    function testPatchOfPublishedIncompleteSealedOpportunitySaves()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);

        $this->assertSame(200, $this->send($this->requestFactory->PATCH_entity($opportunity, ['shortDescription' => 'Nova descrição'])));

        $this->assertSame('Nova descrição', $this->reloaded($opportunity)->shortDescription, 'O edital já publicado não fica preso ao que o CultBR exige para publicar.');
    }

    function testPatchPublishingIncompleteSealedOpportunityIsRefusedOnStatus()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);

        $this->assertSame(400, $this->send($this->requestFactory->PATCH_entity($opportunity, ['status' => Opportunity::STATUS_ENABLED])));

        $errors = $this->responseErrors();
        $this->assertSame(['A oportunidade não pode ser publicada: falta 1 campo.'], $errors['status'] ?? null);
        $this->assertArrayHasKey(self::MISSING_KEY, $errors);
        $this->assertSame(Opportunity::STATUS_DRAFT, $this->reloaded($opportunity)->status);
    }

    function testPatchPublishingSealedOpportunityAlsoNeedsTheCoreFields()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->shortDescription = '';
        $opportunity->vacancies = 0;
        $opportunity->conectaente_segments = [];
        $opportunity->save(true);

        $this->assertSame(400, $this->send($this->requestFactory->PATCH_entity($opportunity, ['status' => Opportunity::STATUS_ENABLED])));

        $errors = $this->responseErrors();
        $this->assertSame(['A oportunidade não pode ser publicada: faltam 3 campos.'], $errors['status'] ?? null);
        $this->assertEqualsCanonicalizing(['shortDescription', 'vacancies', 'conectaente_segments', 'status'], array_keys($errors));
    }

    function testPatchKeepsTheErrorsAddedByTheCoreModules()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);
        $hadModuleConfig = array_key_exists('module.Entities', $this->app->config);
        $moduleConfig = $this->app->config['module.Entities'] ?? null;
        $this->app->config['module.Entities']['requiredAvatar'] = [$opportunity::getClassName() => true];

        try {
            $this->assertSame(400, $this->send($this->requestFactory->PATCH_entity($opportunity, ['status' => Opportunity::STATUS_ENABLED])));
        } finally {
            if ($hadModuleConfig) {
                $this->app->config['module.Entities'] = $moduleConfig;
            } else {
                unset($this->app->config['module.Entities']);
            }
        }

        $errors = $this->responseErrors();
        $this->assertArrayHasKey('file:avatar', $errors);
        $this->assertSame(['A oportunidade não pode ser publicada: faltam 2 campos.'], $errors['status'] ?? null);
    }

    function testPatchKeepsTheErrorsOfHooksRegisteredAfterThePlugin()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
        $isActive = true;
        // como o de um tema: registrado depois do plugin; desligado no fim, porque hook não sai da App
        $this->app->hook('entity(Opportunity).validationErrors', function (&$errors) use (&$isActive) {
            if ($isActive) {
                $errors['themeField'] = ['Campo exigido pelo tema.'];
            }
        });

        try {
            $this->assertSame(400, $this->send($this->requestFactory->PATCH_entity($opportunity, ['shortDescription' => 'Nova descrição'])));
        } finally {
            $isActive = false;
        }

        $this->assertArrayHasKey('themeField', $this->responseErrors());
    }

    function testForceSaveStillPublishesTheIncompleteSealedOpportunity()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);

        $request = $this->requestFactory->PATCH('opportunity', 'single', [$opportunity->id], ['status' => Opportunity::STATUS_ENABLED], headers: ['mapas-force-save' => '1']);
        $this->assertSame(400, $this->send($request), 'A recusa continua sendo relatada, para o gestor saber o que ficou faltando.');

        $this->assertSame(Opportunity::STATUS_ENABLED, $this->reloaded($opportunity)->status, 'O force save do core atravessa a regra do plugin, como atravessa a do core.');
    }

    function testPutRefusesToPublishAndAcceptsSavingWhatIsPublished()
    {
        $draft = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);
        $this->assertSame(400, $this->send($this->PUT($draft, ['status' => Opportunity::STATUS_ENABLED])));
        $this->assertSame([self::MISSING_KEY], array_keys($this->responseErrors()));
        $this->assertSame(Opportunity::STATUS_DRAFT, $this->reloaded($draft)->status);

        $published = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
        $this->assertSame(200, $this->send($this->PUT($published, ['shortDescription' => 'Nova descrição'])), 'Salvar o que já está publicado não é publicar.');
    }

    function testOpportunitySealedAfterBeingPublishedStillSaves()
    {
        $opportunity = $this->coreCompleteOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
        $this->app->disableAccessControl();
        $opportunity->createSealRelation($this->federativeSeal());
        $this->app->enableAccessControl();
        // sem esta pré-condição o teste passaria por a oportunidade não estar selada, que é outro caminho
        $this->assertTrue(Plugin::instance()->sealedOpportunity()->isSealed($this->reloaded($opportunity)));

        $this->assertSame(
            200,
            $this->send($this->requestFactory->PATCH_entity($this->reloaded($opportunity), ['shortDescription' => 'Nova descrição'])),
            'Selar um edital já publicado deixa os trinta campos vazios de uma vez; cobrá-los travaria o gestor sem saída.',
        );
    }

    function testPublishedOpportunityKeepsTheCoreRules()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);

        $this->assertSame(400, $this->send($this->requestFactory->PATCH_entity($opportunity, ['name' => ''])));

        $this->assertArrayHasKey('name', $this->responseErrors(), 'O corte é dos campos do CultBR; o que o core exige continua valendo.');
    }

    function testPublishingByPatchStillRequiresTheFields()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);

        $this->assertSame(400, $this->send($this->requestFactory->PATCH_entity($opportunity, ['status' => Opportunity::STATUS_ENABLED])));

        $this->assertSame(Opportunity::STATUS_DRAFT, $this->reloaded($opportunity)->status, 'É pelo PATCH que o front V2 publica: o status em memória já é 1, e quem sabe da transição é o banco.');
    }

    function testPublishingAgainAfterUnpublishingRequiresTheFields()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
        $this->send($this->PUT($opportunity, ['status' => Opportunity::STATUS_DRAFT]));

        $this->assertSame(400, $this->send($this->requestFactory->POST('opportunity', 'publish', [$this->reloaded($opportunity)->id])));

        $this->assertSame(Opportunity::STATUS_DRAFT, $this->reloaded($opportunity)->status);
    }

    function testTheSimulationMarkDoesNotSurviveTheRequest()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
        $this->send($this->requestFactory->GET('conectaente', 'opportunityRequirements', [$opportunity->id], ajax: true));

        $this->assertSame(
            200,
            $this->send($this->requestFactory->PATCH_entity($this->reloaded($opportunity), ['shortDescription' => 'Nova descrição'])),
            'A marca da aba não pode fazer o salvamento seguinte cobrar o que a publicação exige.',
        );
    }

    function testPublishingWithoutAParLevelIsRefusedNamingIt()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->{CultBrMetadata::PAR_ACTIVITY_ID} = '';
        $opportunity->save(true);

        $this->assertSame(400, $this->send($this->requestFactory->POST('opportunity', 'publish', [$opportunity->id])));

        $this->assertArrayHasKey(CultBrMetadata::PAR_ACTIVITY_ID, json_decode((string) $this->app->response->getBody(), true)['data']);
    }

    function testUnpublishingIncompleteSealedOpportunityIsNotBlocked()
    {
        $byPut = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
        $this->assertSame(200, $this->send($this->PUT($byPut, ['status' => Opportunity::STATUS_DRAFT])));
        $this->assertSame(Opportunity::STATUS_DRAFT, $this->reloaded($byPut)->status);

        $byPatch = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
        $this->assertSame(200, $this->send($this->requestFactory->PATCH_entity($byPatch, ['status' => Opportunity::STATUS_DRAFT])));
        $this->assertSame(Opportunity::STATUS_DRAFT, $this->reloaded($byPatch)->status);
    }

    function testValidatingSealedDraftLeavesThePluginOut()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);

        $this->assertSame(200, $this->send($this->requestFactory->POST('opportunity', 'validateEntity', [$opportunity->id])));
    }

    function testPhaseOfSealedOpportunityIsLeftToTheCore()
    {
        $phase = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false)->lastPhase;
        $context = Plugin::instance()->publicationContext();

        $context->enter($phase);
        try {
            $errors = $phase->validationErrors;
        } finally {
            $context->leave();
        }

        $this->assertArrayNotHasKey(self::MISSING_KEY, $errors);
    }

    function testPublicationMarkEndsWithTheRequest()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);
        $this->send($this->requestFactory->POST('opportunity', 'publish', [$opportunity->id]));

        $this->assertArrayNotHasKey(self::MISSING_KEY, $this->reloaded($opportunity)->validationErrors);
    }

    function testPatchMarkEndsWithTheRequest()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);

        $this->assertSame(400, $this->send($this->requestFactory->PATCH_entity($opportunity, ['status' => Opportunity::STATUS_ENABLED])), 'Durante a requisição a marca vale: o rascunho está publicando.');

        $errors = $this->reloaded($opportunity)->validationErrors;

        $this->assertArrayNotHasKey('status', $errors, 'A marca do status pedido morre com a requisição; fora dela o rascunho não está publicando nada.');
        $this->assertArrayNotHasKey(self::MISSING_KEY, $errors);
    }

    function testCoreAndPluginErrorsOnTheSameFieldAreKept()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_DRAFT);
        $opportunity->registrationFrom = new DateTime('2112-01-01 00:00');
        $opportunity->registrationTo = Opportunity::CONTINUOUS_FLOW_DATE;
        $opportunity->save(true);

        $this->assertSame(400, $this->send($this->requestFactory->PATCH_entity($opportunity, ['status' => Opportunity::STATUS_ENABLED])));

        $messages = $this->responseErrors()['registrationTo'] ?? [];
        $this->assertContains('A data final das inscrições deve ser maior ou igual a data inicial', $messages);
        $this->assertContains('A data final das inscrições não pode ser 01/01/2111, a data que o fluxo contínuo usa quando não há data final.', $messages);
    }

    function testRequestedStatusAppliesOnlyToTheMarkedOpportunity()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
        $other = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $context = Plugin::instance()->publicationContext();

        $context->enter($other, Opportunity::STATUS_DRAFT);
        try {
            $errors = $opportunity->validationErrors;
        } finally {
            $context->leave();
        }

        $this->assertArrayHasKey(self::MISSING_KEY, $errors);
    }

    function testMissingOpportunityStillAnswersNotFound()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertSame(404, $this->send($this->requestFactory->POST('opportunity', 'publish', [999999])));
        $this->assertSame(404, $this->send($this->PUT(999999, ['status' => Opportunity::STATUS_ENABLED])));
    }

    private function assertPublishedAndStamped(Opportunity $opportunity): void
    {
        $published = $this->reloaded($opportunity);

        $this->assertSame(Opportunity::STATUS_ENABLED, $published->status);
        $this->assertLessThan(60, abs($published->conectaente_publishedAt->getTimestamp() - time()));
    }

    private function responseErrors(): array
    {
        return json_decode((string) $this->app->response->getBody(), true)['data'] ?? [];
    }

    private function PUT(Opportunity|int $opportunity, array $payload): ServerRequestInterface
    {
        $id = $opportunity instanceof Opportunity ? $opportunity->id : $opportunity;

        return $this->requestFactory->PATCH('opportunity', 'single', [$id], $payload)->withMethod('PUT');
    }
}
