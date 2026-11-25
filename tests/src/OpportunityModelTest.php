<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Services\SendOutcome;
use DateTime;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\OpportunityMeta;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Entities\User;
use MapasCulturais\Exceptions\BadRequest;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

/**
 * O modelo de oportunidade e a oportunidade gerada a partir dele não herdam selo, data nem estado de envio.
 */
class OpportunityModelTest extends TestCase
{
    use ConectaEnteFixtures;
    use PublicationRequirementsFixtures;
    use RequestFactory;

    const OLD_DATE = '2025-03-10 09:00:00';

    function testTheModelIsBornWithoutTheFederativeSealTheDateAndTheSendState()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->federativeSeal();
        $source = $this->stampedAndSentSource($seal);

        $model = $this->reloaded($this->generateModel($source));

        $this->assertNotContains($seal->id, $this->sealIdsOf($model), 'O modelo selado seria enviado ao CultBR quando alguém o publicasse.');
        $this->assertNull($model->getMetadata(CultBrMetadata::PUBLISHED_AT));
        foreach (CultBrMetadata::SEND_STATE_KEYS as $key) {
            $this->assertNull($model->getMetadata($key), "O modelo herdou {$key} da origem.");
        }
    }

    function testTheModelKeepsTheOtherSealsAndTheContent()
    {
        $this->loginAsSaasSuperAdmin();
        $source = $this->stampedAndSentSource($this->federativeSeal());
        $regularSeal = $this->createSeal();
        $source->createSealRelation($regularSeal);

        $model = $this->reloaded($this->generateModel($source));

        $this->assertContains($regularSeal->id, $this->sealIdsOf($model), 'Só o selo de Ente Federado sai do modelo.');
        $this->assertSame('17832', $model->getMetadata(CultBrMetadata::PAR_EXERCISE_ID), 'O que descreve o edital continua no modelo.');
    }

    function testTheOpportunityGeneratedFromTheModelInheritsNeitherTheSealNorTheDate()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->federativeSeal();
        $model = $this->generateModel($this->stampedAndSentSource($seal));
        // um modelo anterior à correção guarda a data da origem
        $this->app->em->getConnection()->executeStatement(
            'UPDATE opportunity_meta SET value = ? WHERE object_id = ? AND "key" = ?',
            [self::OLD_DATE, $model->id, CultBrMetadata::PUBLISHED_AT],
        );

        $generated = $this->reloaded($this->generateOpportunity($model));

        $this->assertNotContains($seal->id, $this->sealIdsOf($generated));
        $this->assertNull($generated->getMetadata(CultBrMetadata::PUBLISHED_AT), 'A gerada leva a data de outro edital: é dado errado que o validador não pega.');
    }

    function testAModelRefusesTheFederativeSealOutsideTheGeneration()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->federativeSeal();
        $model = $this->generateModel($this->stampedAndSentSource($seal));

        $this->expectException(BadRequest::class);

        $this->reloaded($model)->createSealRelation($this->app->repo(Seal::class)->find($seal->id));
    }

    function testAModelStillAcceptsARegularSeal()
    {
        $this->loginAsSaasSuperAdmin();
        $model = $this->generateModel($this->stampedAndSentSource($this->federativeSeal()));
        $regularSeal = $this->createSeal();

        $this->reloaded($model)->createSealRelation($this->app->repo(Seal::class)->find($regularSeal->id));

        $this->assertContains($regularSeal->id, $this->sealIdsOf($this->reloaded($model)));
    }

    private function federativeSeal(): Seal
    {
        $seal = $this->createSeal();
        $this->createFederativeEntityWithSeal($seal, document: sprintf('%014d', random_int(0, 99999999999999)));

        return $seal;
    }

    private function stampedAndSentSource(Seal $seal): Opportunity
    {
        $source = $this->createOpportunityWithSeal($seal, Opportunity::STATUS_DRAFT);
        $source->conectaente_publishedAt = new DateTime(self::OLD_DATE);
        $source->setMetadata(CultBrMetadata::SEND_STATUS, SendOutcome::SUCCESS);
        $source->setMetadata(CultBrMetadata::PAR_EDITAL_ID, (string) random_int(1, 999999));
        $source->setMetadata(CultBrMetadata::PAR_EXERCISE_ID, '17832');
        $source->save(true);

        return $source;
    }

    private function generateModel(Opportunity $source): Opportunity
    {
        $this->sendFromAnEmptyEntityManager($this->requestFactory->POST('opportunity', 'generatemodel', [$source->id], [
            'name' => 'Modelo de teste',
            'description' => 'Modelo gerado no teste',
        ]));

        return $this->latestWithModelFlag('1');
    }

    private function generateOpportunity(Opportunity $model): Opportunity
    {
        $this->sendFromAnEmptyEntityManager($this->requestFactory->POST('opportunity', 'generateopportunity', [$model->id], [
            'name' => 'Gerada do modelo',
        ]));

        return $this->latestWithModelFlag('0');
    }

    private function sendFromAnEmptyEntityManager($request): void
    {
        $this->app->em->clear();
        $this->login($this->app->repo(User::class)->find($this->app->user->id));
        $this->app->run($request, false);
    }

    private function latestWithModelFlag(string $flag): Opportunity
    {
        $meta = $this->app->repo(OpportunityMeta::class)->findOneBy(['key' => 'isModel', 'value' => $flag], ['id' => 'DESC']);

        return $meta->owner;
    }

    private function sealIdsOf(Opportunity $opportunity): array
    {
        return array_map(fn($relation) => $relation->seal->id, $opportunity->getSealRelations());
    }
}
