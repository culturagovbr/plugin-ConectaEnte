<?php

namespace ConectaEnte\Services;

use ConectaEnte\Metadata\CultBrMetadata;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\i;

/**
 * Traduz a chave de um campo pendente no rótulo que o gestor vê na tela.
 */
final class FieldLabels
{
    public function of(Opportunity $opportunity, string $key): string
    {
        return $this->fromDescription($opportunity::getPropertiesMetadata(), $key);
    }

    /**
     * @param string[] $keys
     * @return array<string, string>
     */
    public function forKeys(Opportunity $opportunity, array $keys): array
    {
        $description = $opportunity::getPropertiesMetadata();
        $labels = [];

        foreach ($keys as $key) {
            $labels[$key] = $this->fromDescription($description, $key);
        }

        return $labels;
    }

    // chaves sem rótulo na descrição da entidade levam o texto da tela do core
    private function fromDescription(array $description, string $key): string
    {
        return match ($key) {
            'rules' => i::__('Regulamento'),
            'term-area' => i::__('Área de Interesse'),
            'registrationRanges' => i::__('Faixas/linhas'),
            'links' => i::__('Links'),
            PublicationRequirements::REGISTRATION_CHANNELS_EMAIL => $description[CultBrMetadata::REGISTRATION_CHANNELS]['label'],
            default => ($description[$key]['label'] ?? '') ?: $key,
        };
    }
}
