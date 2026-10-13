# Componente `<conectaente--send-status>`

Mostra, no cabeçalho da edição da oportunidade, a situação do último envio ao CultBR.

### Propriedades

- *entity **Entity*** : a oportunidade.

### Comportamento

Lê `conectaente_sendStatus` e `conectaente_sendAt` — dois dos metadados que o `OpportunitySender` grava a cada envio — direto da entidade, sem requisição. São metadados públicos, então já chegam no payload da página.

O indicador é um badge discreto, logo abaixo do título, e distingue seis situações:

| Quando | O que diz |
|---|---|
| houve envio | "Enviado", "Falha ao enviar", "O CultBR recusou" ou "Envio simulado", com a data |
| selada, pronta, sem envio ainda | "Aguardando o envio ao CultBR" |
| selada e barrada pelo gate | "Ainda não enviada ao CultBR" |
| sem selo de Ente Federado | nada |

**Simulado não se parece com enviado** — em modo dev nada sai para o CultBR, e o badge diz isso. E **não enviada não se parece com falhou**: era a confusão que o CultEditais colapsa numa frase só.

O motivo gravado em `conectaente_sendReason` **não** é exibido aqui: por que falhou é detalhe de integração e vive na aba de histórico, do `saasSuperAdmin`. O que falta **preencher** continua no card "Campos pendentes" da aba CultBR, que é de quem edita.

As três situações sem envio vêm decididas do servidor, em `$MAPAS.config.conectaenteSendStatus.pendingSendState` (`awaiting`, `ineligible` ou nulo), pelo `Services\SendStatusView` — que pergunta ao mesmo `SendEligibility` do envio, em vez de repetir a regra. Com desfecho gravado o servidor devolve nulo: o desfecho manda, e as onze validações do gate não chegam a rodar.

A cor de cada desfecho sai do token do tema (`--mc-success-500`, `--mc-primary-500`, `--mc-warning-700`, `--mc-danger-700`), e o fundo é o mesmo token clareado por `color-mix` — assim o badge acompanha a paleta de qualquer instalação em vez de fixar uma cor nossa.

A situação é a do último carregamento da página. O envio roda na fila, depois do salvamento: o que foi disparado agora aparece no próximo carregamento.

### Onde é injetado

Pelo hook `template(opportunity.edit.entity-header):end`, que já é específico da edição de oportunidade — fora dela o badge não existe, inclusive na página pública do edital.

O cabeçalho do core não tem ponto de extensão dentro do bloco do título: os quatro hooks do `entity-header` ficam fora dele, e os `<slot>` existem só na versão não editável. Por isso o markup sai no fim do cabeçalho e um **`<teleport defer>`** o leva para dentro de `.entity-header .title`, onde o badge vira a segunda linha da grade, sob o `h2`. O `defer` é necessário porque este componente monta durante a montagem do próprio cabeçalho, quando o `.title` ainda não existe.

Duas dependências do core, que é o preço de não copiar o template dele: a estrutura `.entity-header .title` em grade com o avatar na primeira coluna, e o `gap` de 18px que a margem negativa do badge desconta. Mudando qualquer uma, o badge some ou desencosta **sem erro no console**.

### Importando componente

```php
<?php
$this->import('conectaente--send-status');
?>
```

### Exemplo de uso

```html
<conectaente--send-status :entity="entity"></conectaente--send-status>
```
