<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Repositories\FederativeEntityRepository;
use ConectaEnte\Services\OpportunitySealGuard;
use PHPUnit\Framework\TestCase;

class OpportunitySealGuardTest extends TestCase
{
    private $entities = [];

    protected function setUp(): void
    {
        $this->entities = [
            10 => $this->ente(1, 'Governo A', 10),
            20 => $this->ente(2, 'Governo B', 20),
        ];
    }

    public function testASecondEntitySealIsRefusedNamingBothEntities()
    {
        $message = $this->guard()->conflict(20, [10]);

        $this->assertNotNull($message);
        $this->assertStringContainsString('Governo B', $message);
        $this->assertStringContainsString('Governo A', $message);
    }

    public function testTheConflictIsFoundAmongSeveralAppliedSeals()
    {
        $message = $this->guard()->conflict(20, [99, 98, 10]);

        $this->assertStringContainsString('Governo A', $message);
    }

    public function testASealThatIsNotAnEntityPasses()
    {
        $this->assertNull($this->guard()->conflict(99, [10]));
    }

    public function testAnEntitySealPassesWhenTheOtherAppliedSealsAreNotEntities()
    {
        $this->assertNull($this->guard()->conflict(20, [97, 98]));
    }

    public function testAnEntitySealPassesOnAnOpportunityWithoutSeals()
    {
        $this->assertNull($this->guard()->conflict(20, []));
    }

    public function testApplyingTheSameSealAgainIsIdempotent()
    {
        $this->assertNull($this->guard()->conflict(10, [10]));
        $this->assertNull($this->guard()->conflict('10', [10, '10']));
    }

    public function testASealThatAlreadyBelongsToTheSameEntityDoesNotConflict()
    {
        // um ente tem um selo só (UNIQUE), mas a regra não pode depender disso para ser idempotente
        $this->entities[11] = $this->entities[10];

        $this->assertNull($this->guard()->conflict(11, [10]));
    }

    private function guard()
    {
        $repository = $this->createMock(FederativeEntityRepository::class);
        $repository->method('findBySealId')->willReturnCallback(function ($sealId) {
            return isset($this->entities[(int) $sealId]) ? $this->entities[(int) $sealId] : null;
        });

        return new OpportunitySealGuard($repository);
    }

    // o construtor do core precisa do App
    private function ente($id, $name, $sealId)
    {
        $ente = (new \ReflectionClass(FederativeEntity::class))->newInstanceWithoutConstructor();
        $ref = new \ReflectionProperty(FederativeEntity::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($ente, $id);
        $ente->setName($name);
        $ente->setSealId($sealId);

        return $ente;
    }
}
