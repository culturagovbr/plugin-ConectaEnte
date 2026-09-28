# Componente `<conectaente--federative-entity-par>`

Cascata **Exercício → Meta → Ação → Atividade** do PAR do Ente Federado. É o porte do `mc-federative-entity-par` do Pnab, sem salvamento próprio e sem buscar dados: a árvore chega pronta do chamador, que a pega da rota `conectaente/parInformation`.

### Propriedades

- *exercises **Array** = []* : a árvore do PAR, no formato que a rota devolve — exercícios, com `metas`, `acoes` e `atividades` encaixadas. As chaves são as do contrato do CultBR, por isso ficam em português.
- *modelValue **Object** = null* : `{ parExercicioId, parMetaId, parAcaoId, parAtividadeId }`, as chaves dos metadados da oportunidade. Usar com `v-model`.
- *emptyHint **String** = ''* : substitui a mensagem padrão de árvore vazia.
- *readonly **Boolean** = false* : mostra os rótulos escolhidos, sem os selects.
- *serverErrors **Object** = null* : erros do servidor por metadado, como os de `entity.__validationErrors`.

### Comportamento

Escolher um nível limpa os de baixo, porque a cadeia precisa ser coerente: trocar a meta zera ação e atividade. Os ids vão e voltam como texto — a árvore pode trazê-los como número, e a comparação é sempre entre strings.

Cada nível fica desabilitado até o de cima ter escolha. Quando o nível escolhido não tem filhos, o campo de baixo dá lugar ao aviso ("Não há metas cadastradas para o exercício selecionado.") em vez de um select vazio.

Em `readonly`, o rótulo é o ano do exercício ou o nome do nó; **nó sem nome aparece pelo id**, para a tela nunca mostrar campo vazio onde há escolha feita. Nos selects vale o mesmo: `#id` no lugar do nome ausente.

O erro do servidor tem prioridade sobre o do componente, e o `validate()` (via `ref`) marca os campos e devolve se os quatro níveis formam cadeia completa — quem chama decide o que fazer com o `false`.

Não grava: os metadados vão ao servidor pelo "Salvar" da página, porque a oportunidade selada só é aceita com o edital inteiro válido.

### Importando componente

```php
<?php
$this->import('conectaente--federative-entity-par');
?>
```

### Exemplo de uso

```html
<conectaente--federative-entity-par ref="par" v-model="parSelection" :exercises="exercises" :server-errors="entity.__validationErrors"></conectaente--federative-entity-par>
```
