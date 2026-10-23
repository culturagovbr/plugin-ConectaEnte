<?php

namespace ConectaEnte\Vocabulary;

enum ExecutionType: string
{
    use TranslatedLabel;

    case CULTURAL_EXECUTION = 'Execução cultural';
    case CULTURAL_SPACE_SUBSIDY = 'Subsídio a espaços culturais';
    case CULTURAL_GRANT = 'Bolsa cultural';
    case CULTURAL_AWARD = 'Premiação cultural';
    case TCC_CULTURE_POINTS = 'TCC Pontos de Cultura';
    case TCC_CULTURE_HUBS = 'TCC Pontões de Cultura';
    case CULTURA_VIVA_GRANT = 'Bolsa Cultura Viva';
    case CULTURA_VIVA_AWARD = 'Premiação Cultura Viva';
    case NATIONAL_CONTINUED_ACTIONS_PROGRAM = 'Programa Nacional de Ações Continuadas';
    case NATIONAL_CULTURAL_INFRASTRUCTURE_PROGRAM = 'Programa Nacional de Infraestrutura Cultural';
    case NATIONAL_MANAGER_TRAINING_PROGRAM = 'Programa Nacional de Formação para Gestores';
    case OTHER = 'Outros';
}
