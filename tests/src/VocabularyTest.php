<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Vocabulary\AffirmativeAction;
use ConectaEnte\Vocabulary\AffirmativeActionGroup;
use ConectaEnte\Vocabulary\CulturalStage;
use ConectaEnte\Vocabulary\ExecutionType;
use ConectaEnte\Vocabulary\FundingSource;
use ConectaEnte\Vocabulary\LegalEntityType;
use ConectaEnte\Vocabulary\LegalQuota;
use ConectaEnte\Vocabulary\PriorityTerritory;
use ConectaEnte\Vocabulary\ProponentType;
use ConectaEnte\Vocabulary\RegistrationChannel;
use ConectaEnte\Vocabulary\Segment;
use ConectaEnte\Vocabulary\TargetingField;
use ConectaEnte\Vocabulary\TargetingOption;
use ConectaEnte\Vocabulary\ThematicAgenda;
use POMO\Translations\EntryTranslations;
use POMO\Translations\Translations;
use Tests\Abstract\TestCase;

class VocabularyTest extends TestCase
{
    // a Conecta Ente exige estes valores sem publicá-los; vêm dos contratos de Gestão e Conecta MinC
    const CONTRACT_PROPONENT_TYPES = [
        'pessoa_fisica',
        'mei_microempreendedor_individual',
        'pessoa_juridica_com_fins_lucrativos_empresas',
        'pessoa_juridica_sem_fins_lucrativos',
        'coletivos_e_grupos_informais_sem_cnpj',
        'pontos_de_cultura_com_constituicao_juridica',
        'pontos_de_cultura_sem_constituicao_juridica',
        'organizacoes_da_sociedade_civil_sem_fins_lucrativos',
    ];

    const CONTRACT_REGISTRATION_CHANNELS = ['presencial', 'correio', 'email', 'sistema', 'oral', 'outros'];

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

    function testTargetingOptionsKeepTheCultEditaisKeys()
    {
        $this->assertSame(['__edital_nao_se_direciona__', '__todas_opcoes__'], $this->values(TargetingOption::class));
        $this->assertSame('Todas as opções', TargetingOption::ALL_OPTIONS->text(TargetingField::SEGMENT));
    }

    function testNotTargetedTextIsSpecificToEachField()
    {
        $texts = array_map(fn(TargetingField $field) => TargetingOption::NOT_TARGETED->text($field), TargetingField::cases());

        $this->assertSame([
            'Edital não se direciona a segmentos específicos',
            'Edital não se direciona a etapa específica',
            'Edital não se direciona a pautas específicas',
            'Edital não se direciona a territórios específicos',
        ], $texts);
    }

    function testRegistrationChannelsAreExactlyTheContractValues()
    {
        $this->assertEqualsCanonicalizing(self::CONTRACT_REGISTRATION_CHANNELS, $this->values(RegistrationChannel::class));
        $this->assertSame(
            ['E-mail', 'Presencial', 'Correspondência', 'Oralidade', 'Sistema digital', 'Outros'],
            $this->texts(RegistrationChannel::class)
        );
    }

    function testDefaultProponentLabelsTranslateToTheContract()
    {
        $this->assertSame(['Pessoa Física', 'MEI', 'Coletivo', 'Pessoa Jurídica'], $this->app->config['registration.proponentTypes']);

        $this->assertSame(ProponentType::INDIVIDUAL, ProponentType::tryFromLabel('Pessoa Física'));
        $this->assertSame(ProponentType::MEI, ProponentType::tryFromLabel('MEI'));
        $this->assertSame(ProponentType::COLLECTIVE, ProponentType::tryFromLabel('Coletivo'));
        $this->assertSame(ProponentType::MEI, ProponentType::tryFromLabel(' MEI '));
        $this->assertNull(ProponentType::tryFromLabel('Pessoa Jurídica'));
        $this->assertNull(ProponentType::tryFromLabel('Associação'));
    }

    function testLegalEntityLabelIsRecognizedApartFromUnknownLabels()
    {
        $this->assertTrue(ProponentType::isLegalEntityLabel('Pessoa Jurídica'));
        $this->assertTrue(ProponentType::isLegalEntityLabel(' Pessoa Jurídica '));
        $this->assertFalse(ProponentType::isLegalEntityLabel('MEI'));
        $this->assertFalse(ProponentType::isLegalEntityLabel('Associação'));
    }

    function testLegalEntityTypesCompleteTheLegalEntityProponent()
    {
        $this->assertSame(['Com fins lucrativos', 'Sem fins lucrativos'], $this->values(LegalEntityType::class));
        $this->assertSame(ProponentType::FOR_PROFIT_LEGAL_ENTITY, LegalEntityType::FOR_PROFIT->proponentType());
        $this->assertSame(ProponentType::NON_PROFIT_LEGAL_ENTITY, LegalEntityType::NON_PROFIT->proponentType());
    }

    function testEveryProponentTypeIsAContractValue()
    {
        $this->assertSame([], array_diff($this->values(ProponentType::class), self::CONTRACT_PROPONENT_TYPES));
    }

    function testAffirmativeActionsKeepTheCultEditaisKeys()
    {
        $this->assertSame(
            ['nao_previstas', 'bonus_agentes', 'bonus_tematicas', 'categoria_especifica', 'edital_especifico', 'outra_legislacao'],
            $this->values(AffirmativeAction::class)
        );
        $this->assertSame([
            'Não são previstas outras ações afirmativas',
            'Bônus de pontuação para agentes culturais',
            'Bônus de pontuação para projetos com temáticas específicas',
            'Categoria específica',
            'Edital específico',
            'Outra ação afirmativa prevista em legislação local',
        ], $this->texts(AffirmativeAction::class));
    }

    function testOnlyTheFourBonusAndSpecificActionsAskForGroups()
    {
        $withGroups = array_filter(AffirmativeAction::cases(), fn(AffirmativeAction $action) => $action->hasGroups());

        $this->assertSame(
            [AffirmativeAction::AGENT_BONUS, AffirmativeAction::THEME_BONUS, AffirmativeAction::SPECIFIC_CATEGORY, AffirmativeAction::SPECIFIC_CALL],
            array_values($withGroups)
        );
    }

    function testOnlyNotPlannedIsExclusive()
    {
        $exclusive = array_filter(AffirmativeAction::cases(), fn(AffirmativeAction $action) => $action->isExclusive());

        $this->assertSame([AffirmativeAction::NOT_PLANNED], array_values($exclusive));
    }

    function testAffirmativeActionGroupsKeepTheCultEditaisKeys()
    {
        $this->assertSame([
            'pessoas_negras',
            'pessoas_indigenas',
            'pessoas_deficiencia',
            'mulheres',
            'povos_tradicionais',
            'lgbtqiapn',
            'pessoas_idosas',
            'situacao_rua',
            'outros_vulnerabilizados',
        ], $this->values(AffirmativeActionGroup::class));
        $this->assertSame([
            'Pessoas negras',
            'Pessoas indígenas',
            'Pessoas com deficiência',
            'Mulheres',
            'Povos e comunidades tradicionais',
            'LGBTQIAPN+',
            'Pessoas idosas',
            'Pessoas em situação de rua',
            'Outros grupos vulnerabilizados socialmente',
        ], $this->texts(AffirmativeActionGroup::class));
    }

    function testFundingSourcesKeepTheCultEditaisKeys()
    {
        $this->assertSame(
            ['recursosProprios', 'conveniosParcerias', 'emendasParlamentares', 'remanescentesCiclo1', 'outrasFontes'],
            $this->values(FundingSource::class)
        );
        $this->assertSame(
            [
                'Recursos próprios',
                'Convênios/parcerias com entes federativos',
                'Emendas parlamentares',
                'Recursos remanescentes do ciclo 1',
                'Recursos de outras fontes',
            ],
            $this->texts(FundingSource::class)
        );
    }

    function testLegalQuotasAreTheThreeFromTheLawPlusOpenCompetition()
    {
        $this->assertSame(
            ['Pessoas negras (pretas e pardas)', 'Pessoas indígenas', 'Pessoas com deficiência', 'Ampla concorrência'],
            $this->values(LegalQuota::class)
        );
    }

    function testStoredValueAndPayloadTextDoNotDependOnTheLanguage()
    {
        global $i18n;
        $original = $i18n['default'] ?? null;

        $translations = new Translations();
        foreach ([
            'Execução cultural' => 'Cultural execution',
            'Com fins lucrativos' => 'For profit',
            'Edital não se direciona a pautas específicas' => 'Not aimed at specific agendas',
        ] as $text => $translation) {
            $translations->add_entry(new EntryTranslations(['singular' => $text, 'translations' => [$translation]]));
        }
        $i18n['default'] = $translations;

        try {
            $this->assertSame('Cultural execution', ExecutionType::CULTURAL_EXECUTION->label());
            $this->assertSame('Execução cultural', ExecutionType::CULTURAL_EXECUTION->value);
            $this->assertSame('Execução cultural', ExecutionType::CULTURAL_EXECUTION->text());

            $this->assertSame('For profit', LegalEntityType::FOR_PROFIT->label());
            $this->assertSame('Com fins lucrativos', LegalEntityType::FOR_PROFIT->value);

            $this->assertSame('Not aimed at specific agendas', TargetingOption::NOT_TARGETED->label(TargetingField::THEMATIC_AGENDA));
            $this->assertSame('Edital não se direciona a pautas específicas', TargetingOption::NOT_TARGETED->text(TargetingField::THEMATIC_AGENDA));
        } finally {
            $i18n['default'] = $original;
        }
    }

    private function values(string $vocabulary): array
    {
        return array_map(fn($case) => $case->value, $vocabulary::cases());
    }

    private function texts(string $vocabulary): array
    {
        return array_map(fn($case) => $case->text(), $vocabulary::cases());
    }
}
