<?php

namespace ConectaEnte\Vocabulary;

enum Segment: string
{
    use TranslatedLabel;

    case COLLECTIONS = 'Acervos';
    case ARCHIVES = 'Arquivos';
    case VISUAL_ARTS = 'Artes Visuais';
    case HANDICRAFT = 'Artesanato';
    case AUDIOVISUAL = 'Audiovisual';
    case CAPOEIRA = 'Capoeira';
    case CIRCUS = 'Circo';
    case AFRICAN_ROOTED_CULTURE = 'Cultura de Matriz Africana';
    case NATIVE_PEOPLES_CULTURE = 'Cultura dos Povos Originários';
    case TRADITIONAL_AND_POPULAR_CULTURES = 'Culturas Tradicionais e Populares';
    case DANCE = 'Dança';
    case DESIGN = 'Design';
    case PUBLISHING = 'Edição e produção editorial';
    case FESTIVITIES_AND_CELEBRATIONS = 'Festas e Celebrações';
    case HIP_HOP = 'Hip Hop';
    case VIDEO_GAMES = 'Jogos eletrônicos';
    case LITERATURE = 'Literatura';
    case READER_EDUCATION = 'Mediação e formação de leitores';
    case FASHION = 'Moda';
    case MUSEUM = 'Museu';
    case MUSIC = 'Música';
    case ARCHAEOLOGICAL_HERITAGE = 'Patrimônio Arqueológico';
    case TANGIBLE_CULTURAL_HERITAGE = 'Patrimônio Cultural Material';
    case INTANGIBLE_CULTURAL_HERITAGE = 'Patrimônio Cultural Imaterial';
    case NATURAL_HERITAGE = 'Patrimônio Natural';
    case PERFORMANCE = 'Performance';
    case THEATER = 'Teatro';
    case OTHER = 'Outros';

    /**
     * Texto fixo em pt-br; "Outros" avisa que pede detalhamento.
     */
    public function text(): string
    {
        return $this === self::OTHER ? 'Outros (especificar)' : $this->value;
    }
}
