# Componente `<conectaente--result-rows>`

Card "Grade de contemplados" da aba de resultado: uma linha por inscrição aprovada na fase final, com as colunas do modelo de planilha do edital.

### Propriedades

- *entity **Entity*** : a oportunidade.

### Comportamento

Lê `GET conectaente/resultRows/{id}?page=&pageSize=`, paginado no servidor. Sem planilha (o modelo não tem colunas), o card não aparece.

Cada célula traz o valor e a origem:

| Origem | De onde vem |
|---|---|
| preenchido aqui | célula gravada por quem controla o edital |
| da ficha | campo da ficha ligado à coluna no de/para (o documento, pelo de/para por tipo de proponente) |
| calculado | tipo de proponente em `tipo_agente`, valor por vaga da faixa nas colunas monetárias, nome do agente nas colunas de nome |
| vazio | nenhuma das três |

O valor preenchido aqui vale sobre os outros; texto vazio é a decisão de deixar a célula em branco, e apagar o campo (`null`) devolve o valor da ficha. Valor de coluna de lista passa pela equivalência do de/para.

Cada alteração vai a `POST conectaente/resultRows/{id}`; o servidor recusa só o que a edição quebra (CPF e CNPJ com dígito verificador, inclusive CNPJ alfanumérico, CEP, monetário, opção de lista, um valor por coluna), e a recusa aparece sob a célula. As pendências da linha (obrigatória vazia, valor inválido vindo da ficha, inscrição sem tipo de proponente) pintam a célula; `condicional` não entra, porque a condição é texto livre e quem a cobra é o 422 de linhas.

É uma tabela própria, não o `entity-table` do core: a linha mistura três fontes montadas no servidor, e o `entity-table` lista entidades pela API de consulta.

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
