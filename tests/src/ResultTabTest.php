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

    function testAnUnsealedEditalHasNoResultTab()
    {
        $this->loginAsSaasSuperAdmin();

        $this->assertStringNotContainsString('<conectaente--result-tab', $this->editPage($this->createOpportunity()));
    }
}
