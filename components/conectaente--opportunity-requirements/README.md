# Componente `<conectaente--opportunity-requirements>`

Linha do tempo dos campos pendentes da oportunidade selada, em dois níveis: os grupos com a contagem e, dentro de cada um, os campos com o rótulo e as mensagens do servidor. Quem consulta a rota é o `conectaente--opportunity-tab`.

O desenho é o `section.timeline` do core, o mesmo do acompanhamento da inscrição (`registration-status`): reta, ponto e o corte da reta no último item vêm do tema (`_timeline.scss`); o plugin só pinta o ponto com a cor da oportunidade e transforma o rótulo em botão.

### Os dois grupos

A lista abre no primeiro grupo que tem pendência; "Todos os campos" leva ao primeiro nível e, a partir daí, quem escolhe é o usuário. O primeiro nível traz um ponto por grupo — "Campos nativos" e "Novos campos" — com o número de pendências; clicar abre o grupo na própria timeline, e "Todos os campos" volta. Grupo sem pendência aparece cinza, com um visto, e não abre.

A origem de cada chave vem da rota, não da tela: o prefixo `conectaente_` separa campo do plugin de campo do core, e a regra mora no servidor porque lá ela tem teste.

### Navegação

O rótulo vira botão quando existe `[data-field="<âncora>"]` dentro da aba, e o clique rola a página até o campo parar no seu trecho da linha de leitura — se apenas centralizasse, o destaque cairia no campo de cima ou no vizinho de linha. Sem elemento correspondente o rótulo fica como texto, para não haver link morto. A busca refaz-se a cada resposta da rota, porque um campo pode estar escondido por condição da aba.

### O campo que está sendo lido

Um campo por vez fica ativo: o último cujo topo passou de 30% da tela e que ainda está visível. No primeiro nível, quem fica em destaque é o grupo a que esse campo pertence. O step dele ganha ponto maior e rótulo na cor da oportunidade, o campo recebe `conectaente-field--active`, que o contorna, e o card rola por dentro para não esconder o step.

Campos lado a lado, como Total de vagas e Valor total, dividem a faixa de rolagem da linha. A faixa vai até onde o próximo campo começa: pela altura do campo caberia menos de um giro de roda para cada um.

### Armadilhas desta página

- `IntersectionObserver` não dispara aqui. Quem marca o ativo é um listener de rolagem com `requestAnimationFrame`.
- `behavior: 'smooth'` não serve aqui: em elemento é ignorado, no `scrollIntoView` a animação morre no início e, na janela, o foco que o clique dá ao botão dentro do card rolável a cancela. As duas rolagens são animadas à mão.
- Sem foco na aba o navegador não entrega quadros, e a animação pararia no meio: há um tempo de segurança que leva a rolagem ao destino.
- `$el` não é elemento: o template tem espaços em volta da raiz e o Vue trata o componente como fragmento. Por isso `ref="root"`.

O que aparece, em ordem de prioridade:
- a mensagem de falha, se a consulta falhou;
- "carregando" (`mc-loading`), enquanto a rota não respondeu;
- "Nenhum campo pendente.", quando não há pendência;
- a lista, nos demais casos.

### Propriedades

- *missing **Object|Array|null** = null* : erros por chave, como a rota `conectaente/opportunityRequirements` devolve. `null` enquanto a rota não respondeu; `[]` quando não há pendência.
- *labels **Object|Array*** : rótulo por chave, da mesma rota. Uma chave sem rótulo aparece como ela mesma.
- *anchors **Object|Array** = {}* : o `data-field` de cada chave, da mesma rota. Sem entrada, a própria chave é a âncora.
- *fieldGroups **Object|Array** = {}* : a origem de cada chave (`core` ou `plugin`), da mesma rota. Sem entrada, a chave conta como do core.
- *groupLabels **Object*** : o rótulo de cada grupo, na ordem em que aparecem.
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
<conectaente--opportunity-requirements :missing="missing" :labels="labels" :anchors="anchors" :field-groups="fieldGroups" :group-labels="{ core: 'Campos nativos', plugin: 'Novos campos' }" :loading="loading" :failed="loadFailed"></conectaente--opportunity-requirements>
```
