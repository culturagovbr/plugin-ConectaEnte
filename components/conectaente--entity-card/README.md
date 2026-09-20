# Componente `<conectaente--entity-card>`

Card de um Ente Federado no painel, no `panel-entity-card` do core: o quadro do selo (ou a caixinha `+`) no lugar do retrato, nome, CNPJ e nome do selo ao lado — centrados com o quadro —, as ações à direita e, no corpo, a linha do token no `cardKey` do card de aplicativos, com os avisos por último.

### Propriedades

- *entity **Object*** : o ente, no formato do DTO `FederativeEntityCard` — `id`, `name`, `document` (formatado), `token` (só a máscara) e `seals` (`id`, `name`, `usable`, `validity` em meses, `files.avatar`).
- *trashed **Boolean*** = false : quando verdadeiro, é o card da lixeira — sem Editar, sem `+`, sem X, sem olho/copiar; as ações passam a ser Recuperar e Excluir permanentemente.

### Importando componente

```php
<?php
$this->import('conectaente--entity-card');
?>
```

### Exemplo de uso

```html
<conectaente--entity-card :entity="entity"></conectaente--entity-card>
<conectaente--entity-card :entity="entity" trashed></conectaente--entity-card>
```

### Observações

Cada ente tem um selo só: a caixinha `+` aparece apenas no ente sem selo, e o servidor recusa o segundo. Os avisos (sem selo; selo na lixeira ou arquivado; selo que ganhou validade depois de vinculado) são badges vermelhos, `mc-tag-list` com `danger__background`.

O token chega mascarado pelo servidor. O olho e o copiar abrem, a cada uso, um modal que pede a senha do próprio administrador; `POST federativeEntityToken` só devolve o valor real com a senha confirmada. Copiar leva o token à área de transferência sem mostrá-lo; em página servida por HTTP, onde `navigator.clipboard` não existe, cai no `execCommand('copy')`.

Excluir manda o ente para a lixeira (`DELETE federativeEntity`); na lixeira, Recuperar (`POST federativeEntityUndelete`) e Excluir permanentemente (`DELETE federativeEntityDestroy`). As três abrem o mesmo modal de senha do token — o administrador assina a ação com a própria senha — e recarregam a página. Os botões ficam no `.right` do cabeçalho, ao lado de Editar (o wrapper `panel-entity-card__header-actions` do core apaga o fundo dos botões, por isso não é usado).
