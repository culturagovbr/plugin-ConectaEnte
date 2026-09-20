# Componente `<conectaente--entities-list>`

Lista os Entes Federados cadastrados, cada um com o seu selo, e a busca por nome ou CNPJ.

Os entes vêm prontos do servidor: a view formata o CNPJ, resolve a situação do selo e monta o avatar. O componente filtra, apresenta e cuida das duas ações do selo — cadastrar (caixinha `+`, que abre a busca do `select-entity`) e remover (X sobre o quadro, com confirmação). As duas chamam `federativeEntitySeal` pela `API` do core e recarregam a página.

### Propriedades

- *entities **Array*** : entes a listar. Cada item tem `id`, `name`, `document` (formatado) e `seals`; cada selo tem `id`, `name`, `usable` e `files.avatar` (no formato que o `mc-avatar` lê, ou `null`); `token` é a máscara, nunca o valor.

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

Cada ente tem um selo só: a caixinha `+` aparece apenas no ente sem selo, e o servidor recusa o segundo. O quadro do selo segue o `entity-seals` do core; a única regra própria, em `style.css`, alinha o quadro à esquerda, junto do nome.

Os avisos — ente sem selo, selo na lixeira ou arquivado (`usable` falso) — são badges vermelhos, `mc-tag-list` com `danger__background`, como as demais tags do core.

O token chega **mascarado pelo servidor** (`token` é o prefixo de 6 caracteres seguido de asteriscos, montado pelo DTO `FederativeEntityCard`) e é mostrado no mesmo `cardKey` do card de aplicativos do core. O olho e o copiar abrem, **a cada uso**, um modal que pede a senha do próprio administrador; `POST federativeEntityToken` só devolve o valor real com a senha confirmada. O olho alterna entre o valor revelado e a máscara; copiar leva o token à área de transferência sem mostrá-lo. Copiar usa `navigator.clipboard` e, em página servida por HTTP (onde essa API não existe), cai no `execCommand('copy')`.
