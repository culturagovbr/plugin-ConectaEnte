<?php

use MapasCulturais\i;

return [
    'situacao_success' => i::__('Edital enviado ao CultBR em {data}'),
    'situacao_simulated' => i::__('Envio do edital simulado em {data}: nada saiu para o CultBR'),
    'situacao_rejected' => i::__('Edital recusado pelo CultBR em {data}'),
    'situacao_error' => i::__('Falha ao enviar o edital ao CultBR em {data}'),
    'situacao_awaiting' => i::__('Edital aguardando o envio ao CultBR'),
    'situacao_ineligible' => i::__('Edital ainda não enviado ao CultBR'),
    'situacao_suspended' => i::__('Envio do edital ao CultBR suspenso: a ação escolhida não existe mais no PAR, refaça a seleção na aba CultBR'),
];
