<?php

namespace ConectaEnte\Vocabulary;

enum LegalEntityType: string
{
    use TranslatedLabel;

    case FOR_PROFIT = 'Com fins lucrativos';
    case NON_PROFIT = 'Sem fins lucrativos';

    /**
     * Tipo de proponente do contrato que corresponde a esta pessoa jurídica.
     */
    public function proponentType(): ProponentType
    {
        return match ($this) {
            self::FOR_PROFIT => ProponentType::FOR_PROFIT_LEGAL_ENTITY,
            self::NON_PROFIT => ProponentType::NON_PROFIT_LEGAL_ENTITY,
        };
    }
}
