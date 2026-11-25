# Componente `<conectaente--result-tab>`

Aba "Resultado CultBR" da gestão da oportunidade: o formulário de resultado do edital, montado pela estrutura que o CultBR devolve para o tipo de ação.

### Propriedades

- *entity **Entity*** : a oportunidade.

### Comportamento

Ao montar, lê `GET conectaente/result/{id}`, que devolve o estado do resultado, a estrutura do formulário, as respostas gravadas, as calculadas pelo Mapas, as sugestões e as obrigatórias que faltam. Fora dos estados `pending`, `complete`, `sent`, `sending` e `uncertain`, a aba mostra só a frase do estado.

Com o formulário aberto:

| Questão | Na tela |
|---|---|
| calculada pelo Mapas (inscritos, contemplados, categorias) ou `somente_leitura` | só leitura, com a marca "calculado pelo Mapas" |
| valor repassado | campo editável, com a sugestão do Mapas e o botão "Usar sugestão" |
| `texto`, `textarea`, `número`, `moeda`, `data`, `booleano` | o campo do tipo, com os limites declarados |
| `radio`, `múltipla escolha`, `checkbox` | as opções da lista; a opção com complemento abre o campo de texto, e a exclusiva desmarca as outras |
| `tabela`, `Tabela Dinâmica` | editor de linhas pelas colunas declaradas |
| `anexo`, `múltiplos anexos` | envio de arquivo pelo formato e pelo limite declarados; o arquivo fica privado na oportunidade. Com planilha no edital, a questão da planilha diz que vem da grade, e os anexos não cobrados ficam indisponíveis, com o motivo |
| tipo desconhecido | erro visível, nunca um campo de texto genérico |

As regras de visibilidade (`eq` e `includes`) são avaliadas no navegador a cada resposta, com a mesma lógica do validador do servidor.

**Gravação:** o formulário inteiro vai a `POST conectaente/resultAnswers/{id}` 1,5 s depois da última alteração, uma requisição por vez. O servidor aplica as respostas válidas e devolve o erro das recusadas, que aparece sob a questão. A lateral mostra as obrigatórias que faltam, com um link para cada uma.

**Escolhas** se gravam pelo `id` da opção; a opção com complemento, como `{id, complemento}`.

**Envio:** o botão da lateral chama `POST conectaente/resultSend/{id}`. Ele aparece em `pending`, `complete` e `sent` (onde vira "Retificar no CultBR", quando o `/publicado` tem resultado), e some em `sending` e `uncertain`. A recusa da rota aparece sob o botão, e as pendências devolvidas atualizam a lista do que falta. Em `sending`, a aba relê o estado a cada 5 s, sem tocar nas respostas em edição.

**Último envio:** o card mostra a situação e a data do último desfecho, a mensagem (`lastSend.outcome.message`, montada no servidor), a lista de recusas e o resultado vigente no CultBR. Tudo vem do metadado do envio, não do histórico.

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
