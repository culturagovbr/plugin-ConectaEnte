# Componente `<conectaente--result-mapping>`

Card "Planilha de contemplados" dentro da aba de resultado: o modelo de planilha que o CultBR declara para o edital e o de/para dos campos da ficha.

### Propriedades

- *entity **Entity*** : a oportunidade.

### Eventos

- *saved* : depois de cada gravação, para a grade refazer as células.
- *saving* : se há gravação em curso, para a aba segurar o envio.

### Comportamento

Lê `GET conectaente/resultMapping/{id}`. Sem planilha (`hasSpreadsheet: false`), mostra o motivo que o CultBR devolve, e o resultado sai sem linhas. Com planilha:

- **uma linha por coluna** do modelo, com um `<select>` nativo com **todos os campos da ficha, agrupados por etapa** (`<optgroup>`), e a fase junto quando há mais de uma. O `mc-select` com grupos não serve: ele os desfaz ao fechar. O campo de agente ou espaço vale pelo dado que traz. O tipo que não serve à coluna é recusado no servidor, com o motivo sob o seletor;
- **a sugestão** pelo nome aparece ao lado, marcada como sugestão, e só vale depois de "Confirmar as sugestões", em bloco;
- **o documento do responsável**, por tipo de proponente, com CPF e CNPJ separados; o CNPJ só para MEI e Pessoa Jurídica;
- **a equivalência**: para cada coluna de lista ligada a um campo, os valores da ficha que não estão nas opções do CultBR, cada um com um seletor das opções. A coluna do tipo de agente, sem campo ligado, pede a equivalência dos tipos de proponente do edital.

Cada escolha vai a `POST conectaente/resultMapping/{id}`, uma gravação por vez, na ordem; o servidor recusa campo de outro edital e tipo que não serve à coluna ou ao documento, e a recusa aparece sob o seletor.

### Importando componente

```php
<?php
$this->import('conectaente--result-mapping');
?>
```

### Exemplo de uso

```html
<conectaente--result-mapping :entity="entity"></conectaente--result-mapping>
```
