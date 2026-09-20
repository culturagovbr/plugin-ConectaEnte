<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\FederativeEntity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use ConectaEnte\Entities\FederativeEntitySeal;
use MapasCulturais\Entities\Seal;
use MapasCulturais\Exceptions\PermissionDenied;
use Tests\Abstract\TestCase;
use Tests\Traits\SealDirector;
use Tests\Traits\UserDirector;

class FederativeEntityTest extends TestCase
{
    use SealDirector;
    use UserDirector;

    function testEnteGuardaSeusSelos()
    {
        $this->loginComoSuperAdmin();
        $ente = $this->criarEnte();

        $this->vincular($ente, $this->criarSelo());
        $this->vincular($ente, $this->criarSelo());

        $this->assertCount(2, $this->vinculosDe($ente));
    }

    function testSeloServeAUmEnteSo()
    {
        $this->loginComoSuperAdmin();
        $selo = $this->criarSelo();
        $this->vincular($this->criarEnte(), $selo);
        $outro = $this->criarEnte(name: 'Outro ente', document: '98765432000199');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->app->em->getConnection()->executeStatement(
            'INSERT INTO conectaente_federative_entity_seal (federative_entity_id, seal_id, create_timestamp) VALUES (?, ?, now())',
            [$outro->id, $selo->id]
        );
    }

    function testDocumentoNaoSeRepeteEntreEntes()
    {
        $this->loginComoSuperAdmin();
        $this->criarEnte(document: '12345678000190');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->app->em->getConnection()->executeStatement(
            'INSERT INTO conectaente_federative_entity (name, document, token, create_timestamp) VALUES (?, ?, ?, now())',
            ['Outro ente', '12345678000190', uniqid('token-')]
        );
    }

    function testTokenNaoSeRepeteEntreEntes()
    {
        $this->loginComoSuperAdmin();
        $this->criarEnte(token: 'token-repetido');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->app->em->getConnection()->executeStatement(
            'INSERT INTO conectaente_federative_entity (name, document, token, create_timestamp) VALUES (?, ?, ?, now())',
            ['Outro ente', '98765432000199', 'token-repetido']
        );
    }

    function testExcluirOEnteLevaOsVinculos()
    {
        $this->loginComoSuperAdmin();
        $ente = $this->criarEnte();
        $this->vincular($ente, $this->criarSelo());

        $ente->delete(true);

        $this->assertCount(0, $this->vinculosDe($ente));
    }

    function testExcluirOSeloDeVezLevaOVinculo()
    {
        $this->loginComoSuperAdmin();
        $ente = $this->criarEnte();
        $selo = $this->criarSelo();
        $this->vincular($ente, $selo);

        $this->app->em->getConnection()->executeStatement('DELETE FROM seal WHERE id = ?', [$selo->id]);

        $this->assertCount(0, $this->vinculosDe($ente));
    }

    function testQuemNaoESuperAdminNaoCadastra()
    {
        $this->login($this->userDirector->createUser());

        $this->expectException(PermissionDenied::class);

        $this->criarEnte();
    }

    private function loginComoSuperAdmin(): void
    {
        $this->login($this->userDirector->createUser(['saasSuperAdmin']));
    }

    private function criarEnte(string $name = 'Governo de Santa Catarina', string $document = '12345678000190', ?string $token = null): FederativeEntity
    {
        $ente = new FederativeEntity;
        $ente->name = $name;
        $ente->document = $document;
        $ente->token = $token ?? uniqid('token-');
        $ente->save(true);

        return $ente;
    }

    private function criarSelo(): Seal
    {
        return $this->sealDirector->createSeal(disable_access_control: true);
    }

    private function vincular(FederativeEntity $ente, Seal $selo): FederativeEntitySeal
    {
        $vinculo = new FederativeEntitySeal;
        $vinculo->federativeEntity = $ente;
        $vinculo->seal = $selo;
        $vinculo->save(true);

        return $vinculo;
    }

    private function vinculosDe(FederativeEntity $ente): array
    {
        $this->app->em->clear();

        return $this->app->em->getConnection()->fetchAllAssociative(
            'SELECT id FROM conectaente_federative_entity_seal WHERE federative_entity_id = ?',
            [$ente->id]
        );
    }
}
