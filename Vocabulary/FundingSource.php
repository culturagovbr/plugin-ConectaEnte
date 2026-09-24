<?php

namespace ConectaEnte\Vocabulary;

enum FundingSource: string
{
    use TranslatedLabel;

    case OWN_RESOURCES = 'recursosProprios';
    case FEDERATIVE_AGREEMENTS = 'conveniosParcerias';
    case PARLIAMENTARY_AMENDMENTS = 'emendasParlamentares';
    case FIRST_CYCLE_REMAINDER = 'remanescentesCiclo1';
    case OTHER_SOURCES = 'outrasFontes';

    /**
     * Texto fixo em pt-br da fonte de recurso.
     */
    public function text(): string
    {
        return match ($this) {
            self::OWN_RESOURCES => 'Recursos próprios',
            self::FEDERATIVE_AGREEMENTS => 'Convênios/parcerias com entes federativos',
            self::PARLIAMENTARY_AMENDMENTS => 'Emendas parlamentares',
            self::FIRST_CYCLE_REMAINDER => 'Recursos remanescentes do ciclo 1',
            self::OTHER_SOURCES => 'Recursos de outras fontes',
        };
    }
}
