<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Services\FundingSourceName;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;
use Tests\Traits\RequestFactory;

/**
 * O nome da fonte vai ao CultBR como nome_fonte, e é saneado nas duas pontas: no save e no PATCH.
 */
class FundingSourceNameTest extends TestCase
{
    use PublicationRequirementsFixtures;
    use RequestFactory;

    function testSavingStripsHtmlTrimsAndCutsTheName()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::FUNDING_SOURCES} = $this->blockWithSourceNamed('  <b>Fundação</b> XYZ  ');
        $opportunity->save(true);

        $stored = $this->reloaded($opportunity)->{CultBrMetadata::FUNDING_SOURCES};

        $this->assertSame('Fundação XYZ', $stored->outrasFontes[0]->nomeFonte);
    }

    function testSavingCutsTheNameAtTheLimit()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::FUNDING_SOURCES} = $this->blockWithSourceNamed(str_repeat('a', 300));
        $opportunity->save(true);

        $stored = $this->reloaded($opportunity)->{CultBrMetadata::FUNDING_SOURCES};

        $this->assertSame(FundingSourceName::MAX_LENGTH, mb_strlen($stored->outrasFontes[0]->nomeFonte));
    }

    function testPatchFromTheScreenIsSanitizedToo()
    {
        $opportunity = $this->completeOpportunity(Opportunity::STATUS_DRAFT);
        $this->loginAsSaasSuperAdmin();

        $this->send($this->requestFactory->PATCH_entity($opportunity, [
            CultBrMetadata::FUNDING_SOURCES => $this->blockWithSourceNamed('<script>alert(1)</script>Empresa ABC'),
        ]));

        $stored = $this->reloaded($opportunity)->{CultBrMetadata::FUNDING_SOURCES};

        $this->assertSame('alert(1)Empresa ABC', $stored->outrasFontes[0]->nomeFonte, 'O PATCH passa pelo hook que o CultEditais também usa.');
    }

    function testThePatchHookSanitizesOnItsOwn()
    {
        $data = [CultBrMetadata::FUNDING_SOURCES => $this->blockWithSourceNamed('  <b>Fundação</b> XYZ  ')];

        $this->app->applyHook('PATCH(opportunity.single):data', [&$data]);

        $this->assertSame(
            'Fundação XYZ',
            $data[CultBrMetadata::FUNDING_SOURCES]['outrasFontes'][0]['nomeFonte'],
            'A camada do PATCH saneia sozinha, sem depender do save:before.',
        );
    }

    function testAccentsAndLengthAreCountedInCharactersNotBytes()
    {
        $name = str_repeat('ç', 300);

        $this->assertSame(FundingSourceName::MAX_LENGTH, mb_strlen((new FundingSourceName())->sanitize($name)));
    }

    function testBlockWithoutOtherSourcesIsLeftAlone()
    {
        $service = new FundingSourceName();
        $block = ['houveUtilizacao' => 'sim', 'recursosProprios' => 1500.5];

        $this->assertSame($block, $service->sanitizeBlock($block));
    }

    function testEmptyNameStaysEmpty()
    {
        $service = new FundingSourceName();

        $saneado = $service->sanitizeBlock($this->blockWithSourceNamed('   '));

        $this->assertSame('', $saneado['outrasFontes'][0]['nomeFonte']);
    }

    private function blockWithSourceNamed(string $name): array
    {
        return [
            'houveUtilizacao' => 'sim',
            'outrasFontes' => [['nomeFonte' => $name, 'valor' => 1000]],
        ];
    }
}
