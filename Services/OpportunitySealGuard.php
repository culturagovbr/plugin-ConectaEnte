<?php

namespace ConectaEnte\Services;

use ConectaEnte\Repositories\FederativeEntityRepository;
use MapasCulturais\i;

/**
 * Regra da #44: uma oportunidade não acumula selos de dois entes federados, para o envio nunca ter
 * dúvida sobre qual token usar. Não conhece o App nem as relações do core: quem chama (o hook do
 * plugin) informa os selos que a oportunidade já tem.
 */
class OpportunitySealGuard
{
    private $repository;

    public function __construct(FederativeEntityRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param int   $newSealId      selo que está sendo aplicado
     * @param int[] $appliedSealIds selos que a oportunidade já tem (habilitados e pendentes), sem o da própria relação
     *
     * @return string|null mensagem que nomeia os dois entes; null quando nada conflita
     */
    public function conflict($newSealId, array $appliedSealIds)
    {
        $incoming = $this->repository->findBySealId($newSealId);

        // selo comum: a regra só vale entre selos de ente
        if ($incoming === null) {
            return null;
        }

        foreach (array_unique(array_map('intval', $appliedSealIds)) as $sealId) {
            // o mesmo selo de novo é idempotente
            if ($sealId === (int) $newSealId) {
                continue;
            }

            $applied = $this->repository->findBySealId($sealId);

            if ($applied !== null && $applied->getId() !== $incoming->getId()) {
                return sprintf(
                    $this->t('Não é possível aplicar o selo do ente "%s": esta oportunidade já tem o selo do ente "%s". Uma oportunidade só pode ter o selo de um ente federado.'),
                    $incoming->getName(),
                    $applied->getName()
                );
            }
        }

        return null;
    }

    private function t($text)
    {
        return class_exists(i::class) ? i::__($text) : $text;
    }
}
