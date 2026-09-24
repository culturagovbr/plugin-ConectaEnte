<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Plugin;
use DateTime;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Term;
use MapasCulturais\Entities\User;
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

    function testPatchOfPublishedIncompleteSealedOpportunityIsRefused()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
        $shortDescription = $opportunity->shortDescription;

        $this->assertSame(400, $this->send($this->requestFactory->PATCH_entity($opportunity, ['shortDescription' => 'Nova descrição'])));

        $this->assertSame([self::MISSING_KEY], array_keys($this->responseErrors()));
        $this->assertSame($shortDescription, $this->reloaded($opportunity)->shortDescription);
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
        $opportunity->conectaente_segments = [];
        $opportunity->save(true);

        $this->assertSame(400, $this->send($this->requestFactory->PATCH_entity($opportunity, ['status' => Opportunity::STATUS_ENABLED])));

        $errors = $this->responseErrors();
        $this->assertSame(['A oportunidade não pode ser publicada: faltam 2 campos.'], $errors['status'] ?? null);
        $this->assertEqualsCanonicalizing(['shortDescription', 'conectaente_segments', 'status'], array_keys($errors));
    }

    function testPatchKeepsTheErrorsAddedByTheCoreModules()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
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

    function testForceSaveStillSavesThePublishedIncompleteSealedOpportunity()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);

        $request = $this->requestFactory->PATCH('opportunity', 'single', [$opportunity->id], ['shortDescription' => 'Salva mesmo assim'], headers: ['mapas-force-save' => '1']);
        $this->assertSame(400, $this->send($request));

        $this->assertSame('Salva mesmo assim', $this->reloaded($opportunity)->shortDescription);
    }

    function testPutOfIncompleteSealedOpportunityIsRefused()
    {
        $draft = $this->sealedOpportunity(Opportunity::STATUS_DRAFT, isComplete: false);
        $this->assertSame(400, $this->send($this->PUT($draft, ['status' => Opportunity::STATUS_ENABLED])));
        $this->assertSame([self::MISSING_KEY], array_keys($this->responseErrors()));
        $this->assertSame(Opportunity::STATUS_DRAFT, $this->reloaded($draft)->status);

        $published = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
        $this->assertSame(400, $this->send($this->PUT($published, ['shortDescription' => 'Nova descrição'])));
        $this->assertSame([self::MISSING_KEY], array_keys($this->responseErrors()));
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
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED, isComplete: false);
        $this->send($this->requestFactory->PATCH_entity($opportunity, ['status' => Opportunity::STATUS_ENABLED]));

        $errors = $this->reloaded($opportunity)->validationErrors;

        $this->assertArrayHasKey(self::MISSING_KEY, $errors);
        $this->assertArrayNotHasKey('status', $errors);
    }

    function testCoreAndPluginErrorsOnTheSameFieldAreKept()
    {
        $opportunity = $this->sealedOpportunity(Opportunity::STATUS_ENABLED);
        $opportunity->registrationFrom = new DateTime('2112-01-01 00:00');
        $opportunity->registrationTo = Opportunity::CONTINUOUS_FLOW_DATE;
        $opportunity->save(true);

        $this->assertSame(400, $this->send($this->requestFactory->PATCH_entity($opportunity, ['shortDescription' => 'Nova descrição'])));

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

    private function sealedOpportunity(int $status, bool $isComplete = true): Opportunity
    {
        $opportunity = $this->coreCompleteOpportunity($status, $isComplete);
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal, document: sprintf('%014d', random_int(0, 99999999999999)));
        $opportunity->createSealRelation($seal);

        return $opportunity;
    }

    // completa também para o core, que exige datas e área na publicação
    private function coreCompleteOpportunity(int $status, bool $isComplete = true): Opportunity
    {
        $opportunity = $this->completeOpportunity($status);
        $this->login($this->app->repo(User::class)->find($this->app->user->id));

        $opportunity->registrationFrom = new DateTime('2026-10-01 00:00');
        $opportunity->registrationTo = new DateTime('2026-10-31 18:00');
        $opportunity->terms = ['area' => [$this->app->repo(Term::class)->findOneBy(['taxonomy' => 'area'])->term]];

        if (!$isComplete) {
            $opportunity->{self::MISSING_KEY} = null;
        }

        $opportunity->save(true);

        return $opportunity;
    }

    // a requisição parte do EntityManager vazio, como em produção
    private function send(ServerRequestInterface $request): int
    {
        $this->app->em->clear();
        $this->login($this->app->repo(User::class)->find($this->app->user->id));
        $this->app->reset();
        $this->app->run($request, false);

        return $this->app->response->getStatusCode();
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
