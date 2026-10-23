<?php

namespace ConectaEnte\Vocabulary;

enum AffirmativeActionGroup: string
{
    use TranslatedLabel;

    case BLACK_PEOPLE = 'pessoas_negras';
    case INDIGENOUS_PEOPLE = 'pessoas_indigenas';
    case PEOPLE_WITH_DISABILITIES = 'pessoas_deficiencia';
    case WOMEN = 'mulheres';
    case TRADITIONAL_COMMUNITIES = 'povos_tradicionais';
    case LGBTQIAPN = 'lgbtqiapn';
    case ELDERLY_PEOPLE = 'pessoas_idosas';
    case HOMELESS_PEOPLE = 'situacao_rua';
    case OTHER_VULNERABLE_GROUPS = 'outros_vulnerabilizados';

    /**
     * Texto fixo em pt-br do grupo.
     */
    public function text(): string
    {
        return match ($this) {
            self::BLACK_PEOPLE => 'Pessoas negras',
            self::INDIGENOUS_PEOPLE => 'Pessoas indígenas',
            self::PEOPLE_WITH_DISABILITIES => 'Pessoas com deficiência',
            self::WOMEN => 'Mulheres',
            self::TRADITIONAL_COMMUNITIES => 'Povos e comunidades tradicionais',
            self::LGBTQIAPN => 'LGBTQIAPN+',
            self::ELDERLY_PEOPLE => 'Pessoas idosas',
            self::HOMELESS_PEOPLE => 'Pessoas em situação de rua',
            self::OTHER_VULNERABLE_GROUPS => 'Outros grupos vulnerabilizados socialmente',
        };
    }
}
