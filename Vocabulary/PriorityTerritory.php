<?php

namespace ConectaEnte\Vocabulary;

enum PriorityTerritory: string
{
    use TranslatedLabel;

    case DISASTER_AREA = 'Área atingida por desastre natural';
    case SETTLEMENT_OR_CAMP = 'Assentamento ou acampamento';
    case SOCIAL_HOUSING = 'Conjunto ou empreendimento habitacional de interesse social';
    case FAVELAS_AND_URBAN_COMMUNITIES = 'Favelas e comunidades urbanas';
    case OUTSKIRTS = 'Periferia';
    case LOW_CULTURAL_POLICY_ACCESS_REGIONS = 'Regiões com menor histórico de acesso aos recursos da política pública de cultura';
    case LOW_HDI_REGIONS = 'Regiões com menor índice de Desenvolvimento Humano - IDH';
    case ARCHAEOLOGICAL_AND_HERITAGE_SITES = 'Sítios de arqueológicos e de patrimônio cultural';
    case BORDER_TERRITORY = 'Território de fronteira';
    case TRADITIONAL_COMMUNITIES_TERRITORY = 'Território de povos e comunidades tradicionais';
    case INDIGENOUS_TERRITORY = 'Território indígena';
    case RURAL_TERRITORY = 'Território rural';
    case SPECIAL_SOCIAL_INTEREST_ZONE = 'Zona especial de interesse social';
}
