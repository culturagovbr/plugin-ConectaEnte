<?php

namespace ConectaEnte\Vocabulary;

use ConectaEnte\Metadata\CultBrMetadata;

/**
 * Os quatro campos de direcionamento do edital (segmento, etapa, pauta, território): para cada um, o
 * metadado que guarda a seleção, o vocabulário das opções e o texto de "não se direciona".
 * Substitui o enum da v7, que o PHP 7.2 não compila.
 */
class TargetingField
{
    const SEGMENT = 'segment';
    const CULTURAL_STAGE = 'cultural_stage';
    const THEMATIC_AGENDA = 'thematic_agenda';
    const PRIORITY_TERRITORY = 'priority_territory';

    /**
     * @return string[]
     */
    public static function cases()
    {
        return [self::SEGMENT, self::CULTURAL_STAGE, self::THEMATIC_AGENDA, self::PRIORITY_TERRITORY];
    }

    /**
     * O campo cujo metadado é a chave informada; nulo se a chave não é de direcionamento.
     *
     * @param string $metadataKey
     *
     * @return string|null
     */
    public static function fromMetadataKey($metadataKey)
    {
        foreach (self::cases() as $field) {
            if (self::metadataKey($field) === $metadataKey) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param string $field
     *
     * @return string o metadado que guarda a seleção do campo
     */
    public static function metadataKey($field)
    {
        return self::pick($field, [
            self::SEGMENT => CultBrMetadata::SEGMENTS,
            self::CULTURAL_STAGE => CultBrMetadata::CULTURAL_STAGES,
            self::THEMATIC_AGENDA => CultBrMetadata::THEMATIC_AGENDAS,
            self::PRIORITY_TERRITORY => CultBrMetadata::PRIORITY_TERRITORIES,
        ]);
    }

    /**
     * @param string $field
     *
     * @return string classe do vocabulário (AbstractVocabulary) das opções do campo
     */
    public static function vocabulary($field)
    {
        return self::pick($field, [
            self::SEGMENT => Segment::class,
            self::CULTURAL_STAGE => CulturalStage::class,
            self::THEMATIC_AGENDA => ThematicAgenda::class,
            self::PRIORITY_TERRITORY => PriorityTerritory::class,
        ]);
    }

    /**
     * Texto fixo em pt-br da opção "não se direciona" do campo, o que vai ao payload.
     *
     * @param string $field
     *
     * @return string
     */
    public static function notTargetedText($field)
    {
        return self::pick($field, [
            self::SEGMENT => 'Edital não se direciona a segmentos específicos',
            self::CULTURAL_STAGE => 'Edital não se direciona a etapa específica',
            self::THEMATIC_AGENDA => 'Edital não se direciona a pautas específicas',
            self::PRIORITY_TERRITORY => 'Edital não se direciona a territórios específicos',
        ]);
    }

    private static function pick($field, array $map)
    {
        if (!isset($map[$field])) {
            throw new \InvalidArgumentException(sprintf('Campo de direcionamento desconhecido: "%s".', $field));
        }

        return $map[$field];
    }
}
