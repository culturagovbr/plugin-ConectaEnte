<?php

namespace ConectaEnte\Repositories;

use ConectaEnte\Entities\FederativeEntity;
use ConectaEnte\Exceptions\DuplicateSealException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use MapasCulturais\App;

/**
 * Não checa permissão: quem chama (controller da #42) faz o checkPermission da entidade.
 */
class FederativeEntityRepository
{
    const SEAL_CLASS = 'MapasCulturais\Entities\Seal';

    private $em;

    public function __construct(EntityManagerInterface $em = null)
    {
        $this->em = $em ?: App::i()->em;
    }

    /**
     * @return FederativeEntity|null null quando nenhum ente usa o selo (inclusive se o selo foi
     *                               excluído: a FK remove o ente junto)
     */
    public function findBySealId($sealId)
    {
        return $this->em->getRepository(FederativeEntity::class)->findOneBy(['sealId' => (int) $sealId]);
    }

    /**
     * @return FederativeEntity[]
     */
    public function findAll()
    {
        return $this->em->getRepository(FederativeEntity::class)->findBy([], ['nome' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * @throws DuplicateSealException quando o selo já pertence a outro ente
     */
    public function save(FederativeEntity $entity)
    {
        $this->assertSealFree($entity);

        $entity->setUpdateTimestamp(new \DateTime());

        try {
            $this->em->persist($entity);
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            // corrida entre a checagem e o INSERT: a UNIQUE do banco é a garantia final
            $this->assertSealFree($entity);
            throw $e;
        }

        return $entity;
    }

    public function delete(FederativeEntity $entity)
    {
        $this->em->remove($entity);
        $this->em->flush();
    }

    public function sealExists($sealId)
    {
        return $this->em->getRepository(self::SEAL_CLASS)->find((int) $sealId) !== null;
    }

    private function assertSealFree(FederativeEntity $entity)
    {
        $existing = $this->findBySealId($entity->getSealId());

        if ($existing !== null && $existing->getId() !== $entity->getId()) {
            throw new DuplicateSealException($entity->getSealId(), $existing->getNome());
        }
    }
}
