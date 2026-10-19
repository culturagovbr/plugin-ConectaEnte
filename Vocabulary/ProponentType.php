<?php

namespace ConectaEnte\Vocabulary;

/**
 * Tipos de proponente do contrato Conecta Ente (tipos_proponentes). `pessoa_juridica` não é valor válido.
 */
class ProponentType extends AbstractVocabulary
{
    const INDIVIDUAL = 'pessoa_fisica';
    const MEI = 'mei_microempreendedor_individual';
    const FOR_PROFIT_LEGAL_ENTITY = 'pessoa_juridica_com_fins_lucrativos_empresas';
    const NON_PROFIT_LEGAL_ENTITY = 'pessoa_juridica_sem_fins_lucrativos';
    const COLLECTIVE = 'coletivos_e_grupos_informais_sem_cnpj';
    const CULTURE_POINT_WITH_LEGAL_STATUS = 'pontos_de_cultura_com_constituicao_juridica';
    const CULTURE_POINT_WITHOUT_LEGAL_STATUS = 'pontos_de_cultura_sem_constituicao_juridica';
    const CIVIL_SOCIETY_ORGANIZATION = 'organizacoes_da_sociedade_civil_sem_fins_lucrativos';

    protected static function texts()
    {
        return [
            self::INDIVIDUAL => 'Pessoa Física',
            self::MEI => 'MEI (Microempreendedor Individual)',
            self::FOR_PROFIT_LEGAL_ENTITY => 'Pessoa Jurídica com fins lucrativos (empresas)',
            self::NON_PROFIT_LEGAL_ENTITY => 'Pessoa Jurídica sem fins lucrativos',
            self::COLLECTIVE => 'Coletivos e grupos informais (sem CNPJ)',
            self::CULTURE_POINT_WITH_LEGAL_STATUS => 'Pontos de Cultura com constituição jurídica',
            self::CULTURE_POINT_WITHOUT_LEGAL_STATUS => 'Pontos de Cultura sem constituição jurídica',
            self::CIVIL_SOCIETY_ORGANIZATION => 'Organizações da Sociedade Civil sem fins lucrativos',
        ];
    }
}
