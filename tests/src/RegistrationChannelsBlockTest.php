<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Services\PublicationRequirements;
use ConectaEnte\Vocabulary\RegistrationChannel;
use MapasCulturais\Entities\Opportunity;
use Tests\Abstract\TestCase;
use Tests\ConectaEnte\Traits\PublicationRequirementsFixtures;

/**
 * O bloco no formato que o componente da aba grava: a resposta e as formas com o tipo do contrato.
 */
class RegistrationChannelsBlockTest extends TestCase
{
    use PublicationRequirementsFixtures;

    function testBlockWrittenByTheTabSurvivesTheDatabase()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::REGISTRATION_CHANNELS} = $this->blockAsTheTabWritesIt([
            RegistrationChannel::EMAIL->value => 'editais@municipio.gov.br',
            RegistrationChannel::MAIL->value => 'Envelope no protocolo',
        ]);
        $opportunity->save(true);

        $stored = $this->reloaded($opportunity)->{CultBrMetadata::REGISTRATION_CHANNELS};

        $this->assertSame('sim', $stored->previstasNoEdital);
        $this->assertCount(2, $stored->formas);
        $this->assertSame('email', $stored->formas[0]->tipo);
        $this->assertSame('editais@municipio.gov.br', $stored->formas[0]->descricao);
        $this->assertSame('correio', $stored->formas[1]->tipo);
    }

    function testBlockWrittenByTheTabSatisfiesThePublicationRule()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::REGISTRATION_CHANNELS} = $this->blockAsTheTabWritesIt([
            RegistrationChannel::IN_PERSON->value => 'Na sede da secretaria',
        ]);

        $this->assertSame([], $this->missing($opportunity));
    }

    function testAnsweringNoNeedsNoChannel()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::REGISTRATION_CHANNELS} = ['previstasNoEdital' => 'nao', 'formas' => []];

        $this->assertSame([], $this->missing($opportunity));
    }

    function testUnansweredBlockIsCharged()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::REGISTRATION_CHANNELS} = ['previstasNoEdital' => '', 'formas' => []];

        $this->assertSame(
            [CultBrMetadata::REGISTRATION_CHANNELS => ['O campo "Formas de inscrição previstas no edital" é obrigatório.']],
            $this->missing($opportunity),
            'A aba nasce sem resposta, e a publicação exige que o administrador responda.',
        );
    }

    function testInvalidEmailAnswersOnItsOwnKey()
    {
        $opportunity = $this->completeOpportunity();

        $opportunity->{CultBrMetadata::REGISTRATION_CHANNELS} = $this->blockAsTheTabWritesIt([
            RegistrationChannel::EMAIL->value => 'nao-e-email',
        ]);

        $this->assertSame(
            [PublicationRequirements::REGISTRATION_CHANNELS_EMAIL => ['Informe um e-mail válido.']],
            $this->missing($opportunity),
            'O componente lê esta chave para pôr a mensagem sob o campo de e-mail.',
        );
    }

    function testEveryOfferedChannelIsAcceptedByTheRule()
    {
        $opportunity = $this->completeOpportunity();

        foreach (RegistrationChannel::cases() as $channel) {
            $description = $channel === RegistrationChannel::EMAIL ? 'editais@municipio.gov.br' : 'Detalhe da forma';
            $opportunity->{CultBrMetadata::REGISTRATION_CHANNELS} = $this->blockAsTheTabWritesIt([$channel->value => $description]);

            $this->assertSame([], $this->missing($opportunity), "O canal {$channel->value} é oferecido na aba e precisa passar na regra.");
        }
    }

    /** @return array o bloco como o componente da aba o grava */
    private function blockAsTheTabWritesIt(array $descriptionByType): array
    {
        $formas = array_map(
            fn(string $type, string $description) => ['tipo' => $type, 'descricao' => $description],
            array_keys($descriptionByType),
            $descriptionByType,
        );

        return ['previstasNoEdital' => 'sim', 'formas' => $formas];
    }
}
