# Componente `<conectaente--par-selection>`

Liga a cascata do PAR à oportunidade: busca a árvore na rota do plugin, entrega ao `<conectaente--federative-entity-par>` e devolve a escolha aos metadados da entidade — **sem salvar**.

### Propriedades

- *entity **Entity*** : a oportunidade.
- *classes **String|Array|Object*** : classes do contêiner.

### Comportamento

Busca `conectaente/parInformation/{id}` no `mounted`. Enquanto a resposta não chega, mostra carregamento — sem isso a tela piscaria "Nenhum exercício disponível" antes de saber se há.

A seleção vive no componente, não na entidade: **a cadeia só é gravada quando está completa**, e incompleta grava os quatro metadados como `null`. É o que impede resíduo de uma cadeia antiga de sobreviver a uma troca de exercício, e o que mantém a promessa do edital selado — ou vão os quatro, ou nenhum. Como nada é salvo aqui, o "Salvar" da página continua sendo o único.

Quando a entidade é repovoada pelo servidor, a seleção é relida dela: o que não foi salvo não fica na tela.

Sem árvore, o card mostra o motivo em vez de um aviso genérico — token recusado, CultBR sem responder ou ente sem PAR levam mensagens diferentes, porque só uma delas melhora com o tempo.

### Importando componente

```php
<?php
$this->import('conectaente--par-selection');
?>
```

### Exemplo de uso

```html
<conectaente--par-selection :entity="entity" classes="col-12"></conectaente--par-selection>
```
