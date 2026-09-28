<?php

namespace ConectaEnte\Vocabulary;

enum ProponentType: string
{
    case INDIVIDUAL = 'pessoa_fisica';
    case MEI = 'mei_microempreendedor_individual';
    case COLLECTIVE = 'coletivos_e_grupos_informais_sem_cnpj';
    case FOR_PROFIT_LEGAL_ENTITY = 'pessoa_juridica_com_fins_lucrativos_empresas';
    case NON_PROFIT_LEGAL_ENTITY = 'pessoa_juridica_sem_fins_lucrativos';

    const LEGAL_ENTITY_LABEL = 'Pessoa Jurídica';

    /**
     * Tipo do contrato para o rótulo da configuração; nulo para "Pessoa Jurídica" e rótulo desconhecido.
     */
    public static function tryFromLabel(string $label): ?self
    {
        return match (trim($label)) {
            'Pessoa Física' => self::INDIVIDUAL,
            'MEI' => self::MEI,
            'Coletivo' => self::COLLECTIVE,
            default => null,
        };
    }

    /**
     * Se o rótulo da configuração é "Pessoa Jurídica", cujo tipo vem de LegalEntityType.
     */
    public static function isLegalEntityLabel(string $label): bool
    {
        return trim($label) === self::LEGAL_ENTITY_LABEL;
    }
}
