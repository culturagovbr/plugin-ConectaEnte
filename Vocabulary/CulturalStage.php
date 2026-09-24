<?php

namespace ConectaEnte\Vocabulary;

enum CulturalStage: string
{
    use TranslatedLabel;

    case ACCESS_MEDIATION_AND_ENJOYMENT = 'Acesso, mediação e fruição';
    case TRADE_AND_DISTRIBUTION = 'Comercialização e Distribuição';
    case CREATION = 'Criação';
    case DISSEMINATION_AND_CIRCULATION = 'Difusão e Circulação';
    case TRAINING = 'Formação';
    case MEMORY_AND_PRESERVATION = 'Memória e Preservação';
    case MONITORING_AND_EVALUATION = 'Monitoramento e avaliação';
    case ORGANIZATION_AND_MANAGEMENT = 'Organização e gestão';
    case RESEARCH_AND_REFLECTION = 'Pesquisa e reflexão';
    case PRODUCTION = 'Produção';
    case OTHER = 'Outra (especificar)';
}
