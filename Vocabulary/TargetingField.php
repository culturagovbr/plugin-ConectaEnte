<?php

namespace ConectaEnte\Vocabulary;

use ConectaEnte\Metadata\CultBrMetadata;

enum TargetingField
{
    case SEGMENT;
    case CULTURAL_STAGE;
    case THEMATIC_AGENDA;
    case PRIORITY_TERRITORY;

    /**
     * O metadado que guarda a seleção deste campo.
     */
    public function metadataKey(): string
    {
        return match ($this) {
            self::SEGMENT => CultBrMetadata::SEGMENTS,
            self::CULTURAL_STAGE => CultBrMetadata::CULTURAL_STAGES,
            self::THEMATIC_AGENDA => CultBrMetadata::THEMATIC_AGENDAS,
            self::PRIORITY_TERRITORY => CultBrMetadata::PRIORITY_TERRITORIES,
        };
    }

    /**
     * O vocabulário das opções deste campo.
     *
     * @return class-string
     */
    public function vocabulary(): string
    {
        return match ($this) {
            self::SEGMENT => Segment::class,
            self::CULTURAL_STAGE => CulturalStage::class,
            self::THEMATIC_AGENDA => ThematicAgenda::class,
            self::PRIORITY_TERRITORY => PriorityTerritory::class,
        };
    }

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
