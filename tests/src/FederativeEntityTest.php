<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\FederativeEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use MapasCulturais\Exceptions\PermissionDenied;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\ConectaEnteFixtures;

class FederativeEntityTest extends TestCase
{
    use ConectaEnteFixtures;

    function testFederativeEntityKeepsItsSeals()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();

        $this->linkSeal($federativeEntity, $this->createSeal());
        $this->linkSeal($federativeEntity, $this->createSeal());

        $this->assertCount(2, $this->storedSealLinksOf($federativeEntity));
    }

    function testSealBelongsToASingleFederativeEntity()
    {
        $this->loginAsSaasSuperAdmin();
        $seal = $this->createSeal();
        $this->linkSeal($this->createFederativeEntity(), $seal);
        $otherFederativeEntity = $this->createFederativeEntity(name: 'Outro ente', document: '98765432000199');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->app->em->getConnection()->executeStatement(
            'INSERT INTO conectaente_federative_entity_seal (federative_entity_id, seal_id, create_timestamp) VALUES (?, ?, now())',
            [$otherFederativeEntity->id, $seal->id]
        );
    }

    function testDocumentIsUniqueAcrossFederativeEntities()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntity(document: '12345678000190');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->app->em->getConnection()->executeStatement(
            'INSERT INTO conectaente_federative_entity (name, document, token, create_timestamp) VALUES (?, ?, ?, now())',
            ['Outro ente', '12345678000190', uniqid('token-')]
        );
    }

    function testTokenIsUniqueAcrossFederativeEntities()
    {
        $this->loginAsSaasSuperAdmin();
        $this->createFederativeEntity(token: 'token-repetido');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->app->em->getConnection()->executeStatement(
            'INSERT INTO conectaente_federative_entity (name, document, token, create_timestamp) VALUES (?, ?, ?, now())',
            ['Outro ente', '98765432000199', 'token-repetido']
        );
    }

    function testDeletingFederativeEntityDeletesItsSealLinks()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        $this->linkSeal($federativeEntity, $this->createSeal());

        $federativeEntity->delete(true);

        $this->assertCount(0, $this->storedSealLinksOf($federativeEntity));
    }

    function testHardDeletingSealDeletesItsLink()
    {
        $this->loginAsSaasSuperAdmin();
        $federativeEntity = $this->createFederativeEntity();
        $seal = $this->createSeal();
        $this->linkSeal($federativeEntity, $seal);

        $this->app->em->getConnection()->executeStatement('DELETE FROM seal WHERE id = ?', [$seal->id]);

        $this->assertCount(0, $this->storedSealLinksOf($federativeEntity));
    }

    function testNonSaasSuperAdminCannotRegisterFederativeEntity()
    {
        $this->login($this->userDirector->createUser());

        $this->expectException(PermissionDenied::class);

        $this->createFederativeEntity();
    }
    private function storedSealLinksOf(FederativeEntity $federativeEntity): array
    {
        $this->app->em->clear();

        return $this->app->em->getConnection()->fetchAllAssociative(
            'SELECT id FROM conectaente_federative_entity_seal WHERE federative_entity_id = ?',
            [$federativeEntity->id]
        );
    }
}
