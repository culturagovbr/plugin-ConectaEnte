# Componente `<conectaente--result-mapping>`

Card "Planilha de contemplados" dentro da aba de resultado: o modelo de planilha que o CultBR declara para o edital e o de/para dos campos da ficha.

### Propriedades

- *entity **Entity*** : a oportunidade.

### Eventos

- *saved* : depois de cada gravação, para a grade refazer as células.

### Comportamento

Lê `GET conectaente/resultMapping/{id}`. Sem planilha (`hasSpreadsheet: false`), mostra o motivo que o CultBR devolve, e o resultado sai sem linhas. Com planilha:

- **uma linha por coluna** do modelo, com um `<select>` nativo dos campos da ficha **agrupados por fase e etapa** (`<optgroup>`): há edital com dois campos "CNPJ". Só aparecem os campos cujo tipo serve à coluna (`fieldsByColumn`, decidido no servidor);
- **a sugestão** pelo nome aparece ao lado, marcada como sugestão, e só vale depois de "Confirmar as sugestões", em bloco;
- **o documento do responsável**, por tipo de proponente, com CPF e CNPJ separados; o CNPJ só para MEI e Pessoa Jurídica;
- **a equivalência**: para cada coluna de lista ligada a um campo, os valores da ficha que não estão nas opções do CultBR, cada um com um seletor das opções. A coluna do tipo de agente, sem campo ligado, pede a equivalência dos tipos de proponente do edital.

Cada escolha vai a `POST conectaente/resultMapping/{id}`, uma gravação por vez, na ordem; o servidor recusa campo de outro edital, tipo que não serve à coluna e campo de valor múltiplo como documento, e a recusa aparece sob o seletor.

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
