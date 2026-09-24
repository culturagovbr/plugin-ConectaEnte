<?php

namespace ConectaEnte\Vocabulary;

enum ThematicAgenda: string
{
    use TranslatedLabel;

    case FOOD_CULTURE = 'Cultura Alimentar';
    case DISABILITY_CULTURE = 'Cultura DEF';
    case DIGITAL_CULTURE = 'Cultura Digital';
    case IMMIGRANT_AND_REFUGEE_CULTURES = 'Culturas Imigrantes e Refugiadas';
    case LGBTQIAPN_CULTURE = 'Cultura LGBTQIAPN+';
    case CULTURE_MEMORY_AND_HUMAN_RIGHTS = 'Cultura, Memória e Direitos Humanos';
    case NERD_CULTURE = 'Cultura Nerd';
    case PERIPHERAL_CULTURES = 'Culturas Periféricas';
    case QUILOMBOLA_CULTURE = 'Cultura Quilombola';
    case RURAL_AND_AGROECOLOGICAL_CULTURES = 'Culturas Rurais e Agroecológicas';
    case URBAN_CULTURES = 'Culturas Urbanas';
    case SERTAO_CULTURE = 'Cultura do Sertão';
    case CULTURE_AND_ACCESSIBILITY = 'Cultura e Acessibilidade';
    case CULTURE_AND_CREATIVE_ECONOMY = 'Cultura e Economia Criativa';
    case CULTURE_AND_EDUCATION = 'Cultura e Educação';
    case CULTURE_AND_GENDER = 'Cultura e Gênero';
    case CULTURE_AND_ELDERLY = 'Cultura e Idosos';
    case CULTURE_AND_CHILDHOOD = 'Cultura e Infância';
    case CULTURE_AND_YOUTH = 'Cultura e Juventude';
    case CULTURE_AND_ENVIRONMENT = 'Cultura e Meio ambiente';
    case CULTURE_AND_BLACKNESS = 'Cultura e Negritude';
    case CULTURE_AND_INCARCERATED_PEOPLE = 'Cultura e Pessoas em Situação de Privação de Liberdade';
    case CULTURE_AND_HOMELESS_POPULATION = 'Cultura e População de Rua';
    case CULTURE_AND_ROMANI_PEOPLES = 'Cultura e Povos Ciganos';
    case CULTURE_AND_HEALTH = 'Cultura e Saúde';
    case CULTURE_AND_TOURISM = 'Cultura e Turismo';
    case INDIGENOUS_CULTURES = 'Culturas Indígenas';
    case AFRICAN_ROOTED_TRADITIONAL_CULTURES = 'Culturas Tradicionais de Matriz Africana';
    case OTHER = 'Outra (especificar)';
}
