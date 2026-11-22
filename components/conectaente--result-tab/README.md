# Componente `<conectaente--result-tab>`

Aba "Resultado CultBR" da gestão da oportunidade: o formulário de resultado do edital, montado pela estrutura que o CultBR devolve para o tipo de ação.

### Propriedades

- *entity **Entity*** : a oportunidade.

### Comportamento

Ao montar, lê `GET conectaente/result/{id}`, que devolve o estado do resultado, a estrutura do formulário, as respostas gravadas, as calculadas pelo Mapas, as sugestões e as obrigatórias que faltam. Fora dos estados `pending` e `complete`, a aba mostra só a frase do estado.

Com o formulário aberto:

| Questão | Na tela |
|---|---|
| calculada pelo Mapas (inscritos, contemplados, categorias) ou `somente_leitura` | só leitura, com a marca "calculado pelo Mapas" |
| valor repassado | campo editável, com a sugestão do Mapas e o botão "Usar sugestão" |
| `texto`, `textarea`, `número`, `moeda`, `data`, `booleano` | o campo do tipo, com os limites declarados |
| `radio`, `múltipla escolha`, `checkbox` | as opções da lista; a opção com complemento abre o campo de texto, e a exclusiva desmarca as outras |
| `tabela`, `Tabela Dinâmica` | editor de linhas pelas colunas declaradas |
| `anexo`, `múltiplos anexos` | o nome do modelo, quando a estrutura o traz |
| tipo desconhecido | erro visível, nunca um campo de texto genérico |

As regras de visibilidade (`eq` e `includes`) são avaliadas no navegador a cada resposta, com a mesma lógica do validador do servidor.

**Gravação:** o formulário inteiro vai a `POST conectaente/resultAnswers/{id}` 1,5 s depois da última alteração, uma requisição por vez. O servidor aplica as respostas válidas e devolve o erro das recusadas, que aparece sob a questão. A lateral mostra as obrigatórias que faltam, com um link para cada uma.

**Escolhas** se gravam pelo `id` da opção; a opção com complemento, como `{id, complemento}`.

### Onde é injetado

Pelo hook `template(opportunity.edit.tabs):end`, só na raiz selada por Ente Federado e só para quem tem `@control` sobre a oportunidade (`layouts/parts/conectaente/result-tab.php`).

### Importando componente

```php
<?php
$this->import('conectaente--result-tab');
?>
```

### Exemplo de uso

```html
<conectaente--result-tab :entity="entity"></conectaente--result-tab>
```
