<?php

namespace ConectaEnte\Payload;

use ConectaEnte\Metadata\CultBrMetadata;
use ConectaEnte\Services\FieldLabels;
use MapasCulturais\Entities\Opportunity;
use MapasCulturais\i;

/**
 * Confere o payload montado: o contrato exige as 30 chaves presentes e nenhuma nula.
 */
final class PayloadValidation
{
    /** A chave do contrato e o campo do Mapas de onde ela sai, para o motivo nomear o que o gestor vê. */
    const SOURCES = [
        'id_exercicio' => CultBrMetadata::PAR_EXERCISE_ID,
        'id_meta' => CultBrMetadata::PAR_GOAL_ID,
        'id_acao' => CultBrMetadata::PAR_ACTION_ID,
        'id_atividade' => CultBrMetadata::PAR_ACTIVITY_ID,
        'numero_e_titulo_edital' => 'name',
        'forma_de_execucao' => CultBrMetadata::EXECUTION_TYPE,
        'data_publicacao_edital' => CultBrMetadata::PUBLISHED_AT,
        'detalhamento_objeto' => 'shortDescription',
        'numero_previsto_vagas' => 'vacancies',
        'valor_total_edital' => 'totalResource',
        'data_inicial_prazo_inscricao' => 'registrationFrom',
        'data_final_prazo_inscricao' => 'registrationTo',
        'tipos_proponentes' => 'registrationProponentTypes',
        'segmentos_artistico_culturais' => CultBrMetadata::SEGMENTS,
        'segmento_artistico_cultural_especificar' => CultBrMetadata::SEGMENTS_OTHER,
        'etapas_fazer_cultural' => CultBrMetadata::CULTURAL_STAGES,
        'etapa_fazer_cultural_especificar' => CultBrMetadata::CULTURAL_STAGES_OTHER,
        'pautas_especificas' => CultBrMetadata::THEMATIC_AGENDAS,
        'pauta_especifica_especificar' => CultBrMetadata::THEMATIC_AGENDAS_OTHER,
        'categorias_edital' => 'registrationRanges',
        'recursos_territorios_prioritarios' => CultBrMetadata::PRIORITY_TERRITORIES,
        'links_da_pagina_pnab' => 'links',
        'pdf_edital' => 'rules',
        'recursos_outras_fontes' => CultBrMetadata::FUNDING_SOURCES,
        'tipos_formas_inscricao' => CultBrMetadata::REGISTRATION_CHANNELS,
        'reserva_vagas_cotas' => CultBrMetadata::QUOTA_RESERVATION,
        'outras_modalidades_acoes_afirmativas' => CultBrMetadata::AFFIRMATIVE_ACTIONS,
    ];

    /** Chaves que o plugin resolve sozinho: nulo nelas é inconsistência interna, não campo a preencher. */
    const INTERNAL = ['id', 'status', 'ente_federado'];

    public function __construct(private FieldLabels $fieldLabels)
    {
    }

    /**
     * Os motivos por chave do contrato, ou vazio quando o payload está íntegro.
     *
     * @return array<string, string>
     */
    public function errors(Opportunity $opportunity, array $payload): array
    {
        $errors = [];

        foreach ([...array_keys(self::SOURCES), ...self::INTERNAL] as $key) {
            // array e objeto vazios são resposta, não ausência: o contrato os aceita
            if (array_key_exists($key, $payload) && $payload[$key] !== null) {
                continue;
            }

            $errors[$key] = in_array($key, self::INTERNAL, true)
                ? sprintf(i::__('O campo %s do CultBR não foi resolvido: isto é inconsistência do plugin, não dado a preencher.'), $key)
                : sprintf(i::__('O campo "%s" está vazio, e o CultBR o exige.'), $this->fieldLabels->of($opportunity, self::SOURCES[$key]));
        }

        return $errors;
    }
}
