# Contexto
Um plugin que permite a qualquer estado ou município publicar seus editais de fomento à
cultura e enviá-los automaticamente à Plataforma CultBR — o que hoje só funciona na
instalação do Ministério da Cultura.

# Instalação

O estilo é SCSS em `assets-src/sass/`, compilado pelo `@mapas/scripts` como nos demais plugins, e o CSS gerado **não é versionado**: instalar exige o build, a partir de `src/` do repositório principal.

```bash
pnpm install --filter @mapas/plugin-conectaente
cd plugins/ConectaEnte && pnpm run build
```

Sem o build a tela de Entes Federados não abre: `assets/` é a raiz que o core usa para resolver os componentes. O estilo é registrado nos grupos `app` e `app-v2`, porque BaseV1 imprime um e BaseV2 o outro.

Revelar ou copiar o token, excluir, recuperar e excluir permanentemente pedem a **senha local** do administrador — o hash `localAuthenticationPassword`, o mesmo do MultipleLocalAuth. Conta sem senha local recebe a orientação de definir uma em Conta e Privacidade. A senha confirmada vale por uma **janela de 2 minutos** (fixa, contada da última digitação, presa à sessão e fechada no logout); `CONECTAENTE_PASSWORD_WINDOW` muda a duração em segundos, e `0` volta a pedir a senha a cada ação.

# Configuração

Sete variáveis de ambiente, todas com default. **Duas delas erram em silêncio se não forem declaradas:**

| variável | default | o que faz |
|---|---|---|
| `CONECTAENTE_HOST` | `https://ente.conecta.hmg.cultbr.cultura.gov.br` | a origem da API do CultBR. **O default é homologação**: uma instalação de produção que não declare esta variável envia os editais para o ambiente de testes, sem nenhum aviso. Só a origem, sem prefixo de caminho — `/health` e `/` ficam fora de `/api/v1`. |
| `CONECTAENTE_MODE` | `dev` | `live` fala com o CultBR; `dev` **simula**, servindo as respostas de `fixtures/`. **O default é `dev`**: sem declarar `live`, a instalação parece integrada e não consulta nem envia nada. |
| `CONECTAENTE_PASSWORD_WINDOW` | `120` | segundos que a senha local confirmada vale; `0` pede a senha a cada ação. |
| `CONECTAENTE_PAR_SYNC_INTERVAL_MINUTES` | `30` | intervalo entre os ciclos que aquecem o cache do PAR. |
| `CONECTAENTE_PAR_CACHE_TTL_MINUTES` | `10` | quanto a árvore do PAR fica no cache antes de ser buscada de novo. O padrão é curto de propósito: o CultBR altera o PAR em intervalo imprevisível, e árvore vencida faz o gestor escolher ente que não existe mais. |
| `CONECTAENTE_SEND_MAX_ATTEMPTS` | `3` | tentativas de envio do edital antes de o desfecho virar falha definitiva. Só indisponibilidade retenta; recusa do CultBR encerra na primeira. |
| `CONECTAENTE_SEND_RETRY_DELAY_SECONDS` | `30` | espera entre uma tentativa de envio e a seguinte. |

O ambiente é lido na criação do container: **editar o `.env` com a instalação de pé não muda nada** até ela subir de novo.

# Modo de trabalho

Em `dev`, toda requisição ao CultBR é resolvida por um arquivo de exemplo: a rota `{host}/nome-da-rota` procura `fixtures/nome-da-rota.json`, e sem o arquivo a resposta é uma falha registrada no log. Serve para trabalhar com o CultBR indisponível ou sem credencial. O envio do edital não chega ao transporte em `dev`: o desfecho é `simulated` direto, porque a fixture não representaria o edital enviado. Nesse modo o cabeçalho de toda página ganha uma faixa dizendo que os dados são simulados — ninguém deve confundir exemplo com dado do CultBR. Em `live`, o filtro por CNPJ volta a valer e a árvore do PAR é a do ente autenticado.

# A aba CultBR

A oportunidade que tem selo de um Ente Federado ganha uma aba própria na edição, com os campos que o CultBR exige e que o Mapas não tem. A aba é injetada por hook (`template(opportunity.edit.tabs):end`) — **nenhum arquivo do core é sobreposto** —, aparece e some conforme o selo é aplicado ou removido, e não existe em instalação sem Ente Federado cadastrado.

Os metadados do plugin usam o prefixo `conectaente_` (`conectaente_executionType`, `conectaente_segments`, `conectaente_parExercicioId`, …). Ao lado dos campos, a lista **"Campos pendentes"** mostra o que falta para o edital estar completo, com o rótulo de cada um, e clicar num item rola até o campo e o destaca.

**A publicação é barrada, o salvamento não.** Enquanto faltar campo, publicar devolve erro nomeando cada um; salvar um edital **já publicado** não cobra nada — a distinção é lida do status no banco, não do status em memória, porque no `PATCH` os dois diferem. A regra é uma só, no servidor, e é a mesma que alimenta a lista de pendências.

**Na oportunidade selada o salvamento é tudo ou nada.** Os campos do core que salvam sozinhos (vagas, valor, datas, tipos de proponente, faixas) vão junto com o resto: enquanto faltar algo, cada um desses salvamentos automáticos volta erro, com o valor na tela sem gravar. O caminho é preencher tudo e salvar, ou despublicar, completar e publicar.

**Dois escapes conhecidos, herdados do core:** o cabeçalho `mapas-force-save` grava mesmo com erro — é recurso do core para salvar com pendência, e o carimbo de data de publicação sai junto —, e `Entity::save()` chamado direto não valida. Em ambos, a validação antes do envio ao CultBR continua sendo a última barreira.

# Data de publicação

O CultBR exige a data de publicação do edital, e o core não a guarda. O plugin carimba a dele **só na transição para publicado**, nunca em salvamento de edital já publicado, e **nunca sobrescreve** data existente. Instalação que já rodou o CultEditais tem a data no `publishedTimestamp` do tema Pnab: ela é lida **uma vez** como valor inicial da chave do plugin, e depois não é mais tocada. Edital publicado antes do plugin e sem data nenhuma: o administrador informa, na própria aba. A cópia de um edital não leva a data — ganha a dela quando for publicada.

# Dados do PAR

Os quatro campos do Plano de Ação e Referência — exercício, meta, ação e atividade — são uma cascata alimentada pela árvore que o CultBR devolve para o Ente Federado do selo. A seleção é **tudo ou nada**: enquanto os quatro não estiverem escolhidos, nenhum é gravado, para não persistir cadeia quebrada.

A árvore vem do cache, e é buscada quando ele está frio, porque o CultBR altera o PAR em intervalo curto e imprevisível. Quem abre a oportunidade nunca fica preso esperando a API: se ela não responde, a tela diz que a indisponibilidade é do CultBR e o edital segue editável — os quatro campos só são cobrados na publicação.

Fora da requisição, um ciclo aquece o cache: ele seleciona os Entes Federados que têm selo aplicado a alguma oportunidade viva — os únicos cuja árvore alguém vai abrir — e enfileira **uma busca por ente**, espaçadas para não ocupar a fila de jobs da instalação. Salvar um Ente Federado enfileira a busca só dele. Ente sem selo, ou com selo que ninguém aplicou, fica de fora do ciclo e paga a busca ao vivo na primeira abertura.

# Envio do edital ao CultBR

O edital selado por Ente Federado vai ao CultBR sozinho, sem botão: quem dispara é o save. Três situações enfileiram o envio — publicar uma oportunidade selada e completa, editar uma já publicada, e aplicar o selo numa que já estava publicada. Fase e oportunidade sem selo de Ente nunca disparam.

O envio não acontece dentro da requisição. O gatilho só enfileira um job, que o cron executa depois, com `PUT /api/v1/oportunidades/{id}` e o token do Ente Federado dono do selo. A fila deduplica **por edital**: salvar três vezes seguidas deixa um job, não três.

## O que o gestor vê

Três metadados na oportunidade registram o desfecho da última tentativa:

| chave | o que guarda |
|---|---|
| `conectaente_sendStatus` | `success`, `simulated`, `rejected` ou `error` |
| `conectaente_sendReason` | por que não foi aceito; vazio quando foi |
| `conectaente_sendAt` | quando a tentativa aconteceu |

`rejected` é recusa do CultBR — o edital chegou lá e foi negado, e o motivo nomeia o campo recusado quando a resposta traz essa informação. `error` é falha nossa ou indisponibilidade que esgotou as tentativas. A distinção importa: recusa pede corrigir o edital, falha pede olhar o log.

**Esses metadados são públicos.** A API do Mapas os entrega sem sessão, como qualquer metadado não marcado como privado. Por isso o motivo gravado traz só frase estável e status HTTP; mensagem de exceção e erro de conexão — que revelam host, DNS e caminho de arquivo — ficam apenas no `$app->log`.

## Retentativa

Só indisponibilidade retenta: 5xx e falha de conexão. Recusa do CultBR encerra na primeira tentativa, porque repetir não mudaria a resposta. São três tentativas com trinta segundos entre elas (`CONECTAENTE_SEND_MAX_ATTEMPTS` e `CONECTAENTE_SEND_RETRY_DELAY_SECONDS`); esgotadas, o desfecho vira `error` e a fila para.

O envio tem tempos próprios — 30 s para conectar, 60 no total —, mais largos que os da leitura, que desiste em 10 s porque qualquer consulta pode estar rodando dentro da requisição, com alguém esperando na tela. Escrever no CultBR só acontece na fila, onde ninguém espera, e desistir cedo demais registraria falha num edital que ele apenas demorou a processar.

## Antes de enviar

O job confere a elegibilidade na hora de executar, não só no momento do disparo: entre enfileirar e rodar, o edital pode ter ido para a lixeira ou perdido um campo obrigatório. São três condições, e o motivo registrado em log diz qual falhou, nomeando os campos pelo rótulo que aparece na tela.

# Selo do Ente Federado

O selo cadastrado no Ente Federado deve ser criado **sem período de validade**: um selo com prazo expiraria a integração sem aviso. O plugin recusa o vínculo de selo com validade — a mensagem manda editar o selo e remover a validade — e, se um selo já vinculado ganhar validade depois, a listagem avisa. Só relação de selo concedida traz a oportunidade para a integração. A caixinha `+` e o cadastro só oferecem selos que nenhum Ente Federado usa — inclusive na lixeira, que continua ocupando o selo.

# Lixeira

Excluir um Ente Federado manda-o para a lixeira, no padrão do core (`status`): ele some da listagem e deixa de integrar, mas continua ocupando CNPJ e selo, e o token continua no banco. Recuperar devolve tudo; excluir permanentemente apaga ente, vínculo e token.

# Dumps

A tabela `conectaente_federative_entity` guarda o token de cada Ente Federado em texto claro. **Nunca inclua essa tabela em dump compartilhado** — todo dump com ela é um vazamento de credencial.

# Testes

A partir de `tests/` do repositório principal:

```bash
docker compose -f docker-compose.yml -f ../src/plugins/ConectaEnte/tests/docker-compose.yml \
  run --rm mapas pu /var/www/tests/ConectaEnte
```

Apontar sempre para `/var/www/tests/ConectaEnte`. Apontar para `/var/www/tests` rodaria a suíte do core sob a configuração do plugin.
