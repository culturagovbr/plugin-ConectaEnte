<?php

namespace Tests\ConectaEnte;

use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

/**
 * Onde a aba de resultado aparece: na raiz selada, para quem controla o edital.
 */
class ResultTabTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    function testTheSealedEditalGetsTheResultTab()
    {
        $page = $this->editPage($this->sealedOpportunity(Opportunity::STATUS_ENABLED));

        $this->assertStringContainsString('<conectaente--result-tab :entity="entity">', $page);
        $this->assertStringContainsString('"conectaente--result-tab":', $page, 'O template do componente vai junto com a página.');
    }

    function testTheResultTabComesBeforeTheHistory()
    {
        $page = $this->editPage($this->sealedOpportunity(Opportunity::STATUS_ENABLED));
        preg_match('/<conectaente--opportunity-tab :entity="entity">(.*?)<\/conectaente--opportunity-tab>/s', $page, $tab);
        $template = file_get_contents(PLUGINS_PATH . 'ConectaEnte/components/conectaente--opportunity-tab/template.php');

        $this->assertStringContainsString('<conectaente--result-tab', $tab[1], 'O slot fica entre a aba CultBR e a do histórico.');
        $this->assertGreaterThan(strpos($template, 'slug="cultbr"'), strpos($template, '<slot>'));
        $this->assertGreaterThan(strpos($template, '<slot>'), strpos($template, 'slug="cultbr-historico"'));
    }

    function testAnUnsealedEditalHasNoResultTab()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertStringNotContainsString('<conectaente--result-tab', $this->editPage($this->createOpportunity()));
    }
}
