# Componente `<conectaente--entities-list>`

Lista os entes federados cadastrados, com o selo de cada um e a busca por nome ou CNPJ.

Os dados vêm prontos do servidor: a view formata CNPJ e resolve a situação de cada selo, e o componente só filtra e apresenta. Não faz requisição.

### Propriedades

- *entities **Array*** : entes a listar. Cada item tem `id`, `name`, `document` (formatado) e `seals`, e cada selo tem `id`, `name` e `usable`.

### Importando componente

```php
<?php
$this->import('conectaente--entities-list');
?>
```

### Exemplo de uso

```html
<conectaente--entities-list :entities='<?= json_encode($entities) ?>'></conectaente--entities-list>
```

### Observações

`usable` é falso quando o selo foi para a lixeira ou arquivado — nesse caso as oportunidades com ele deixam de ser reconhecidas, e a lista marca o selo em vermelho. Token nunca chega aqui.
