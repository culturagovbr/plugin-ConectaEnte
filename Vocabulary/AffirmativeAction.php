<?php

namespace ConectaEnte\Vocabulary;

enum AffirmativeAction: string
{
    use TranslatedLabel;

    case NOT_PLANNED = 'nao_previstas';
    case AGENT_BONUS = 'bonus_agentes';
    case THEME_BONUS = 'bonus_tematicas';
    case SPECIFIC_CATEGORY = 'categoria_especifica';
    case SPECIFIC_CALL = 'edital_especifico';
    case OTHER_LEGISLATION = 'outra_legislacao';

    /**
     * Texto fixo em pt-br da modalidade.
     */
    public function text(): string
    {
        return match ($this) {
            self::NOT_PLANNED => 'Não são previstas outras ações afirmativas',
            self::AGENT_BONUS => 'Bônus de pontuação para agentes culturais',
            self::THEME_BONUS => 'Bônus de pontuação para projetos com temáticas específicas',
            self::SPECIFIC_CATEGORY => 'Categoria específica',
            self::SPECIFIC_CALL => 'Edital específico',
            self::OTHER_LEGISLATION => 'Outra ação afirmativa prevista em legislação local',
        };
    }

    /**
     * Se a modalidade exige escolher os grupos atendidos.
     */
    public function hasGroups(): bool
    {
        return in_array($this, [self::AGENT_BONUS, self::THEME_BONUS, self::SPECIFIC_CATEGORY, self::SPECIFIC_CALL], true);
    }

    /**
     * Se a modalidade exclui as demais.
     */
    public function isExclusive(): bool
    {
        return $this === self::NOT_PLANNED;
    }
}
