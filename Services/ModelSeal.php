<?php

namespace ConectaEnte\Services;

use ConectaEnte\Entities\FederativeEntitySeal;
use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\Entities\Seal;

final class ModelSeal
{
    const MODEL_KEY = 'isModel';

    public function isModel(Opportunity $opportunity): bool
    {
        return (int) $opportunity->getMetadata(self::MODEL_KEY) === 1;
    }

    /** Se o selo é o de algum Ente Federado cadastrado. */
    public function isFederativeSeal(Seal $seal): bool
    {
        return App::i()->repo(FederativeEntitySeal::class)->findOneBySeal($seal) !== null;
    }

    /** Tira do modelo os selos de Ente Federado que a geração copiou da oportunidade de origem. */
    public function release(Opportunity $opportunity): void
    {
        if (!$this->isModel($opportunity)) {
            return;
        }

        $app = App::i();

        foreach ($opportunity->getSealRelations() as $relation) {
            if (!$this->isFederativeSeal($relation->seal)) {
                continue;
            }

            // quem gera o modelo pode não ter controle sobre o selo do Ente Federado
            $app->disableAccessControl();

            try {
                $relation->delete(true);
            } finally {
                $app->enableAccessControl();
            }
        }
    }
}
