<?php

namespace Tests\ConectaEnte;

use Tests\Abstract\TestCase;

/**
 * O edital selado é tudo ou nada: quem edita seus campos não grava por conta própria.
 */
class OpportunityFieldComponentsTest extends TestCase
{
    // o cadastro de Ente Federado é entidade do plugin, e nele gravar do próprio componente é o esperado
    const FEDERATIVE_ENTITY_COMPONENTS = ['conectaente--entity-card', 'conectaente--entity-form'];

    function testNoFieldComponentWritesOnItsOwn()
    {
        foreach ($this->fieldComponentScripts() as $name => $script) {
            $this->assertDoesNotMatchRegularExpression(
                '/\.save\(|\bPOST\(|\bPATCH\(|\bPUT\(|\bDELETE\(/',
                $script,
                "O componente {$name} grava sozinho; no edital selado o \"Salvar\" da página é o único.",
            );
        }
    }

    function testTheParCascadeIsAmongTheComponentsUnderTheRule()
    {
        $this->assertArrayHasKey('conectaente--federative-entity-par', $this->fieldComponentScripts());
    }

    /** @return array<string, string> */
    private function componentScripts(): array
    {
        $scripts = [];

        foreach (glob(PLUGINS_PATH . 'ConectaEnte/components/*/script.js') as $path) {
            $scripts[basename(dirname($path))] = file_get_contents($path);
        }

        return $scripts;
    }

    /** @return array<string, string> */
    private function fieldComponentScripts(): array
    {
        return array_diff_key($this->componentScripts(), array_flip(self::FEDERATIVE_ENTITY_COMPONENTS));
    }
}
