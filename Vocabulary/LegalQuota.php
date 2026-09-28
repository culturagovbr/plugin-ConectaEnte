<?php

namespace ConectaEnte\Vocabulary;

enum LegalQuota: string
{
    use TranslatedLabel;

    case BLACK_PEOPLE = 'Pessoas negras (pretas e pardas)';
    case INDIGENOUS_PEOPLE = 'Pessoas indígenas';
    case PEOPLE_WITH_DISABILITIES = 'Pessoas com deficiência';
    case OPEN_COMPETITION = 'Ampla concorrência';
}
