<?php

use MapasCulturais\i;

return [
    'estado_unavailable' => i::__('O resultado só pode ser preenchido em edital publicado ou arquivado.'),
    'estado_noParEdital' => i::__('Este edital ainda não foi aceito pelo CultBR. O resultado fica disponível depois do primeiro envio aceito do edital.'),
    'estado_federativeEntityChanged' => i::__('Este edital foi enviado ao CultBR por outro Ente Federado. O resultado só pode ser enviado pelo Ente Federado que enviou o edital; quem administra os Entes Federados resolve, devolvendo o selo ou reenviando o edital.'),
    'estado_awaitingFinalPublication' => i::__('Aguardando a publicação final. Publique o resultado final do edital para preencher o formulário.'),
    'estado_noForm' => i::__('O CultBR não tem formulário de resultado para a ação deste edital.'),
    'estado_cultbrUnavailable' => i::__('Não foi possível ler o formulário de resultado no CultBR: {motivo}'),
    'estado_noTab' => i::__('Este edital não tem resultado a enviar ao CultBR.'),
    'motivo_unreachable' => i::__('o CultBR não respondeu. Tente de novo em alguns minutos.'),
    'motivo_rejected' => i::__('o CultBR recusou a consulta. Confira o token do Ente Federado.'),
    'motivo_status' => '',
    'motivo_null' => '',
    'erro_arquivo' => i::__('Não foi possível enviar o arquivo agora.'),
    'arquivo_grande' => i::__('O arquivo passa de {tamanho} MB.'),
    'remover_arquivo' => i::__('Remover o arquivo {nome}'),
    'grade_com_pendencia' => i::__('Linhas com pendência na grade de contemplados: {total}.'),
    'enviar' => i::__('Enviar ao CultBR'),
    'retificar' => i::__('Retificar no CultBR'),
    'situacao_success' => i::__('Aceito'),
    'situacao_rejected' => i::__('Recusado pelo CultBR'),
    'situacao_error' => i::__('Não enviado'),
    'situacao_uncertain' => i::__('Sem resposta do CultBR'),
    'situacao_simulated' => i::__('Simulado'),
    'linhas_ocultas' => i::__('E mais {total} linhas recusadas, não listadas aqui.'),
    'resultado_vigente' => i::__('Resultado vigente no CultBR: {tipo}, id {id}.'),
    'tipo_2' => i::__('final'),
    'tipo_3' => i::__('retificado'),
    'erro_envio' => i::__('Não foi possível pedir o envio agora. Tente de novo em alguns minutos.'),
];
