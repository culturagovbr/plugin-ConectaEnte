<?php

namespace ConectaEnte\Services;

use MapasCulturais\App;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\i;

/**
 * Completa a descrição dos campos do core que a aba usa: o rótulo que falta e a obrigatoriedade.
 */
final class CoreFieldsDescription
{
    const REQUIRED = ['vacancies', 'totalResource'];

    /** O core descreve o campo sem rótulo; sem ele a tela mostra só o asterisco e a lista, a chave crua. */
    const MISSING_LABELS = ['registrationProponentTypes' => 'Tipos do proponente'];

    public function __construct(private SealedOpportunity $sealedOpportunity)
    {
    }

    /** Ajusta a descrição que a página e a lista de pendências leem. */
    public function complete(array &$propertiesMetadata): void
    {
        foreach (self::MISSING_LABELS as $field => $label) {
            if (isset($propertiesMetadata[$field]) && empty($propertiesMetadata[$field]['label'])) {
                $propertiesMetadata[$field]['label'] = i::__($label);
            }
        }

        $opportunity = $this->editedOpportunity();

        if (!$opportunity || !$this->sealedOpportunity->isSealed($opportunity)) {
            return;
        }

        foreach (self::REQUIRED as $field) {
            if (isset($propertiesMetadata[$field])) {
                $propertiesMetadata[$field]['required'] = true;
            }
        }
    }

    private function editedOpportunity(): ?Opportunity
    {
        $controller = App::i()->view->controller ?? null;
        $entity = $controller?->id === 'opportunity' && $controller->action === 'edit' ? $controller->requestedEntity : null;

        return $entity instanceof Opportunity ? $entity : null;
    }
}
