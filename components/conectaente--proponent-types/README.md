# Componente `<conectaente--proponent-types>`

Tipos de proponente do edital, com as caixas dependentes de vinculação de agente coletivo. É a cópia de `modules/Opportunities/components/opportunity-proponent-types`, com **uma diferença de comportamento: não grava**. Saiu também o `setup` de textos do original, que era código morto — nem o template usa `text()`, nem existe `texts.php`.

O original chama `entity.save()` a cada clique. Na aba CultBR isso não serve: a oportunidade selada só é salva com todos os campos do edital preenchidos, então cada clique numa caixa devolveria a lista inteira de pendências em vermelho. Aqui o valor fica na entidade e vai junto com o "Salvar" da página.

Uma instalação com o tema Pnab não aplica a versão dele deste componente — o que se perde é o asterisco de obrigatório e a condição `canConfigureAgentRelation`.

A raiz publica `data-field="registrationProponentTypes"`, que é como a lista de campos pendentes encontra o campo — o original não publica nada, e sem isso o rótulo "Tipos do proponente" fica sem link.

### Propriedades

- *entity **Entity*** : a oportunidade.

### Importando componente

```php
<?php
$this->import('conectaente--proponent-types');
?>
```

### Exemplo de uso

```html
<conectaente--proponent-types :entity="entity"></conectaente--proponent-types>
```
