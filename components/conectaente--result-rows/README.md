# Componente `<conectaente--result-rows>`

Card "Grade de contemplados" da aba de resultado: uma linha por inscrição aprovada na fase final, com as colunas do modelo de planilha do edital.

### Propriedades

- *entity **Entity*** : a oportunidade.
- *mappingVersion **Number** = 0* : muda a cada gravação do de/para e refaz a grade.

### Eventos

- *saved* : depois de cada célula gravada.

### Comportamento

Lê o resumo em `GET conectaente/resultRows/{id}` (colunas, total e pendências) e entrega as linhas ao `entity-table` do core, que as busca em `GET /api/conectaente/resultRows?opportunity={id}&@limit=&@page=`, com a paginação no cabeçalho `API-Metadata`. Sem planilha (o modelo não tem colunas), o card não aparece.

Cada célula traz o valor e a origem:

| Origem | De onde vem |
|---|---|
| preenchido aqui | célula gravada por quem controla o edital |
| da ficha | campo da ficha ligado à coluna no de/para (o documento, pelo de/para por tipo de proponente) |
| calculado | tipo de proponente em `tipo_agente`, valor por vaga da faixa nas colunas monetárias, nome do agente nas colunas de nome |
| vazio | nenhuma das três |

O valor preenchido aqui vale sobre os outros; texto vazio é a decisão de deixar a célula em branco, e apagar o campo (`null`) devolve o valor da ficha. Valor de coluna de lista passa pela equivalência do de/para.

Cada alteração vai a `POST conectaente/resultRows/{id}`; o servidor recusa só o que a edição quebra (CPF e CNPJ com dígito verificador, inclusive CNPJ alfanumérico, CEP, monetário, opção de lista, um valor por coluna), e a recusa aparece sob a célula. As pendências da linha (obrigatória vazia, valor inválido vindo da ficha, inscrição sem tipo de proponente) pintam a célula; `condicional` não entra, porque a condição é texto livre e quem a cobra é o 422 de linhas.

A grade mostra sempre todas as colunas (`all-headers`): sem isso, o `entity-table` mostra só as obrigatórias. Cada célula é um slot do `entity-table`, com o editor do tipo da coluna; depois de gravar, a tabela relê a página e o resumo recalcula as pendências.

### Importando componente

```php
<?php
$this->import('conectaente--result-rows');
?>
```

### Exemplo de uso

```html
<conectaente--result-rows :entity="entity"></conectaente--result-rows>
```
