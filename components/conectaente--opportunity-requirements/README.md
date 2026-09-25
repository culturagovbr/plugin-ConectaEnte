# Componente `<conectaente--opportunity-requirements>`

Lista os campos pendentes da oportunidade selada: o rótulo de cada campo seguido das mensagens do servidor. É só apresentação. Quem consulta a rota é o `conectaente--opportunity-tab`.

O que aparece, em ordem de prioridade:
- a mensagem de falha, se a consulta falhou;
- "carregando" (`mc-loading`), enquanto a rota não respondeu;
- "Nenhum campo pendente.", quando não há pendência;
- a lista, nos demais casos.

### Propriedades

- *missing **Object|Array|null** = null* : erros por chave, como a rota `conectaente/opportunityRequirements` devolve. `null` enquanto a rota não respondeu; `[]` quando não há pendência.
- *labels **Object|Array*** : rótulo por chave, da mesma rota. Uma chave sem rótulo aparece como ela mesma.
- *loading **Boolean** = false* : consulta em andamento. Esmaece a lista.
- *failed **Boolean** = false* : a última consulta falhou.

### Importando componente

```php
<?php
$this->import('conectaente--opportunity-requirements');
?>
```

### Exemplo de uso

```html
<conectaente--opportunity-requirements :missing="missing" :labels="labels" :loading="loading" :failed="loadFailed"></conectaente--opportunity-requirements>
```
