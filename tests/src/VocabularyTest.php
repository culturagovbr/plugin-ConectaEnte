<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Vocabulary\CulturalStage;
use ConectaEnte\Vocabulary\ExecutionType;
use ConectaEnte\Vocabulary\PriorityTerritory;
use ConectaEnte\Vocabulary\Segment;
use ConectaEnte\Vocabulary\ThematicAgenda;
use POMO\Translations\EntryTranslations;
use POMO\Translations\Translations;
use Tests\Abstract\TestCase;

class VocabularyTest extends TestCase
{
    function testExecutionTypeListIsTheCultEditaisOne()
    {
        $this->assertSame([
            'Execução cultural',
            'Subsídio a espaços culturais',
            'Bolsa cultural',
            'Premiação cultural',
            'TCC Pontos de Cultura',
            'TCC Pontões de Cultura',
            'Bolsa Cultura Viva',
            'Premiação Cultura Viva',
            'Programa Nacional de Ações Continuadas',
            'Programa Nacional de Infraestrutura Cultural',
            'Programa Nacional de Formação para Gestores',
            'Outros',
        ], $this->values(ExecutionType::class));
    }

    function testSegmentListIsTheSniicOne()
    {
        $this->assertSame([
            'Acervos',
            'Arquivos',
            'Artes Visuais',
            'Artesanato',
            'Audiovisual',
            'Capoeira',
            'Circo',
            'Cultura de Matriz Africana',
            'Cultura dos Povos Originários',
            'Culturas Tradicionais e Populares',
            'Dança',
            'Design',
            'Edição e produção editorial',
            'Festas e Celebrações',
            'Hip Hop',
            'Jogos eletrônicos',
            'Literatura',
            'Mediação e formação de leitores',
            'Moda',
            'Museu',
            'Música',
            'Patrimônio Arqueológico',
            'Patrimônio Cultural Material',
            'Patrimônio Cultural Imaterial',
            'Patrimônio Natural',
            'Performance',
            'Teatro',
            'Outros',
        ], $this->values(Segment::class));
    }

    function testOtherSegmentTextAsksForDetail()
    {
        $this->assertSame('Outros (especificar)', Segment::OTHER->text());
        $this->assertSame('Teatro', Segment::THEATER->text());
    }

    function testCulturalStageListFollowsTheCultEditaisOrder()
    {
        $this->assertSame([
            'Acesso, mediação e fruição',
            'Comercialização e Distribuição',
            'Criação',
            'Difusão e Circulação',
            'Formação',
            'Memória e Preservação',
            'Monitoramento e avaliação',
            'Organização e gestão',
            'Pesquisa e reflexão',
            'Produção',
            'Outra (especificar)',
        ], $this->values(CulturalStage::class));
    }

    function testThematicAgendaListIsTheSniicOneWithoutTheNoAgendaOption()
    {
        $this->assertSame([
            'Cultura Alimentar',
            'Cultura DEF',
            'Cultura Digital',
            'Culturas Imigrantes e Refugiadas',
            'Cultura LGBTQIAPN+',
            'Cultura, Memória e Direitos Humanos',
            'Cultura Nerd',
            'Culturas Periféricas',
            'Cultura Quilombola',
            'Culturas Rurais e Agroecológicas',
            'Culturas Urbanas',
            'Cultura do Sertão',
            'Cultura e Acessibilidade',
            'Cultura e Economia Criativa',
            'Cultura e Educação',
            'Cultura e Gênero',
            'Cultura e Idosos',
            'Cultura e Infância',
            'Cultura e Juventude',
            'Cultura e Meio ambiente',
            'Cultura e Negritude',
            'Cultura e Pessoas em Situação de Privação de Liberdade',
            'Cultura e População de Rua',
            'Cultura e Povos Ciganos',
            'Cultura e Saúde',
            'Cultura e Turismo',
            'Culturas Indígenas',
            'Culturas Tradicionais de Matriz Africana',
            'Outra (especificar)',
        ], $this->values(ThematicAgenda::class));
    }

    function testPriorityTerritoryListIsTheCultEditaisOne()
    {
        $this->assertSame([
            'Área atingida por desastre natural',
            'Assentamento ou acampamento',
            'Conjunto ou empreendimento habitacional de interesse social',
            'Favelas e comunidades urbanas',
            'Periferia',
            'Regiões com menor histórico de acesso aos recursos da política pública de cultura',
            'Regiões com menor índice de Desenvolvimento Humano - IDH',
            'Sítios de arqueológicos e de patrimônio cultural',
            'Território de fronteira',
            'Território de povos e comunidades tradicionais',
            'Território indígena',
            'Território rural',
            'Zona especial de interesse social',
        ], $this->values(PriorityTerritory::class));
    }

    function testStoredValueAndPayloadTextDoNotDependOnTheLanguage()
    {
        global $i18n;
        $original = $i18n['default'] ?? null;

        $translations = new Translations();
        foreach ([
            'Execução cultural' => 'Cultural execution',
        ] as $text => $translation) {
            $translations->add_entry(new EntryTranslations(['singular' => $text, 'translations' => [$translation]]));
        }
        $i18n['default'] = $translations;

        try {
            $this->assertSame('Cultural execution', ExecutionType::CULTURAL_EXECUTION->label());
            $this->assertSame('Execução cultural', ExecutionType::CULTURAL_EXECUTION->value);
            $this->assertSame('Execução cultural', ExecutionType::CULTURAL_EXECUTION->text());
        } finally {
            $i18n['default'] = $original;
        }
    }

    private function values(string $vocabulary): array
    {
        return array_map(fn($case) => $case->value, $vocabulary::cases());
    }
}
