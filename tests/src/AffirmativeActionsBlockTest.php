<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Vocabulary\AffirmativeAction;
use ConectaEnte\Vocabulary\AffirmativeActionGroup;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * O bloco no formato que o componente da aba grava: opções, subcategorias por opção e a descrição.
 */
class AffirmativeActionsBlockTest extends TestCase
{
    use PublicationRequirementsFixtures;

    function testBlockWrittenByTheTabSurvivesTheDatabase()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::AFFIRMATIVE_ACTIONS} = [
            'opcoes' => [AffirmativeAction::AGENT_BONUS->value, AffirmativeAction::OTHER_LEGISLATION->value],
            AffirmativeAction::AGENT_BONUS->value => [AffirmativeActionGroup::WOMEN->value],
            'outra_legislacao_descricao' => 'Lei municipal 123/2024',
        ];
        $opportunity->save(true);

        $stored = $this->reloaded($opportunity)->{CultBrMetadata::AFFIRMATIVE_ACTIONS};

        $this->assertSame(['bonus_agentes', 'outra_legislacao'], $stored->opcoes);
        $this->assertSame(['mulheres'], $stored->bonus_agentes);
        $this->assertSame('Lei municipal 123/2024', $stored->outra_legislacao_descricao);
    }

    function testEveryOptionOfferedByTheTabIsAcceptedByTheRule()
    {
        $opportunity = $this->completeOpportunity();

        foreach (AffirmativeAction::cases() as $action) {
            $block = ['opcoes' => [$action->value]];

            if ($action->hasGroups()) {
                $block[$action->value] = [AffirmativeActionGroup::WOMEN->value];
            }

            if ($action === AffirmativeAction::OTHER_LEGISLATION) {
                $block['outra_legislacao_descricao'] = 'Lei municipal 123/2024';
            }

            $opportunity->{CultBrMetadata::AFFIRMATIVE_ACTIONS} = $block;

            $this->assertSame([], $this->missing($opportunity), "A opção {$action->value} é oferecida na aba e precisa passar na regra.");
        }
    }

    function testEveryGroupOfferedByTheTabIsAcceptedByTheRule()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::AFFIRMATIVE_ACTIONS} = [
            'opcoes' => [AffirmativeAction::THEME_BONUS->value],
            AffirmativeAction::THEME_BONUS->value => array_column(AffirmativeActionGroup::cases(), 'value'),
        ];

        $this->assertSame([], $this->missing($opportunity));
    }

    function testUnansweredBlockIsCharged()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::AFFIRMATIVE_ACTIONS} = ['opcoes' => []];

        $this->assertSame(
            [CultBrMetadata::AFFIRMATIVE_ACTIONS => ['Selecione pelo menos uma opção.']],
            $this->missing($opportunity),
            'A aba nasce sem marcação, e a publicação exige que o administrador responda.',
        );
    }

    function testEachActionMissingItsGroupsIsNamed()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::AFFIRMATIVE_ACTIONS} = [
            'opcoes' => [AffirmativeAction::AGENT_BONUS->value, AffirmativeAction::THEME_BONUS->value],
        ];

        $this->assertSame([CultBrMetadata::AFFIRMATIVE_ACTIONS => [
            'Selecione pelo menos uma subcategoria de "Bônus de pontuação para agentes culturais".',
            'Selecione pelo menos uma subcategoria de "Bônus de pontuação para projetos com temáticas específicas".',
        ]], $this->missing($opportunity), 'Sem o nome da ação, as duas mensagens seriam iguais e uma sumiria.');
    }

    function testNotPlannedAloneSatisfiesTheRule()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::AFFIRMATIVE_ACTIONS} = ['opcoes' => [AffirmativeAction::NOT_PLANNED->value]];

        $this->assertSame([], $this->missing($opportunity));
    }
}
