<?php

namespace ConectaEnte\Vocabulary;

/**
 * Modalidades de ação afirmativa.
 */
class AffirmativeAction extends AbstractVocabulary
{
    const NOT_PLANNED = 'nao_previstas';
    const AGENT_BONUS = 'bonus_agentes';
    const THEME_BONUS = 'bonus_tematicas';
    const SPECIFIC_CATEGORY = 'categoria_especifica';
    const SPECIFIC_CALL = 'edital_especifico';
    const OTHER_LEGISLATION = 'outra_legislacao';

    protected static function texts()
    {
        return [
            self::NOT_PLANNED => 'Não são previstas outras ações afirmativas',
            self::AGENT_BONUS => 'Bônus de pontuação para agentes culturais',
            self::THEME_BONUS => 'Bônus de pontuação para projetos com temáticas específicas',
            self::SPECIFIC_CATEGORY => 'Categoria específica',
            self::SPECIFIC_CALL => 'Edital específico',
            self::OTHER_LEGISLATION => 'Outra ação afirmativa prevista em legislação local',
        ];
    }
}
