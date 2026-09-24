<?php

namespace ConectaEnte\Vocabulary;

enum TargetingField
{
    case SEGMENT;
    case CULTURAL_STAGE;
    case THEMATIC_AGENDA;
    case PRIORITY_TERRITORY;

    /**
     * Texto fixo em pt-br da opção "não se direciona" deste campo, o que vai ao payload.
     */
    public function notTargetedText(): string
    {
        return match ($this) {
            self::SEGMENT => 'Edital não se direciona a segmentos específicos',
            self::CULTURAL_STAGE => 'Edital não se direciona a etapa específica',
            self::THEMATIC_AGENDA => 'Edital não se direciona a pautas específicas',
            self::PRIORITY_TERRITORY => 'Edital não se direciona a territórios específicos',
        };
    }
}
