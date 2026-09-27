<?php

namespace ConectaEnte\Services;

use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;

/**
 * Marca como obrigatórios, na edição da oportunidade selada, os campos do core que a publicação exige.
 */
final class RequiredCoreFields
{
    const FIELDS = ['vacancies', 'totalResource'];

    public function __construct(private SealedOpportunity $sealedOpportunity)
    {
    }

    /** Marca os campos na descrição que a página envia ao cliente. */
    public function markRequired(array &$propertiesMetadata): void
    {
        $opportunity = $this->editedOpportunity();

        if (!$opportunity || !$this->sealedOpportunity->isSealed($opportunity)) {
            return;
        }

        foreach (self::FIELDS as $field) {
            if (isset($propertiesMetadata[$field])) {
                $propertiesMetadata[$field]['required'] = true;
            }
        }
    }

    /** A oportunidade que esta requisição edita, ou nulo quando a requisição é outra. */
    private function editedOpportunity(): ?Opportunity
    {
        $controller = App::i()->view->controller ?? null;
        $entity = $controller?->id === 'opportunity' && $controller->action === 'edit' ? $controller->requestedEntity : null;

        return $entity instanceof Opportunity ? $entity : null;
    }
}
