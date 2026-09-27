# Componente `<conectaente--opportunity-ranges>`

Faixas/linhas do edital (`registrationRanges`), com rótulo, número de vagas e valor de cada uma. É a cópia de `modules/Opportunities/components/opportunity-ranges-config`, com **uma diferença de comportamento: não grava**.

O original salva a entidade a cada edição de faixa e a cada remoção (`entity.save(3000)`). Na aba CultBR isso não serve: a oportunidade selada só é salva com todos os campos do edital preenchidos, então cada digitação devolveria a lista inteira de pendências em vermelho. Aqui o valor fica na entidade e vai junto com o "Salvar" da página. Do `autoSaveRange` sobrou só a parte que não grava — descartar a faixa cujo rótulo ficou vazio —, agora em `discardIfEmpty`.

A raiz mantém a classe `opportunity-ranges-config`, que é de onde vem o estilo do tema, e acrescenta `data-field="registrationRanges"`, por onde a lista de campos pendentes encontra o campo.

Faixa não é campo obrigatório: a regra de publicação não cobra que existam faixas, ela compara a soma das que existem com Total de vagas e com Valor total. Por isso o componente fica ao lado desses dois campos, no mesmo card.

### Propriedades

- *entity **Entity*** : a oportunidade.

### Importando componente

```php
<?php
$this->import('conectaente--opportunity-ranges');
?>
```

### Exemplo de uso

```html
<conectaente--opportunity-ranges :entity="entity"></conectaente--opportunity-ranges>
```
