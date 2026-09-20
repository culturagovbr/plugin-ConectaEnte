# Componente `<conectaente--entities-list>`

Lista os Entes Federados cadastrados em duas abas — ativos, com busca por nome ou CNPJ, e Lixeira — renderizando um `conectaente--entity-card` por ente.

Os entes vêm prontos do servidor, já separados por situação; o componente só filtra e distribui. Toda ação sobre um ente vive no card.

### Propriedades

- *entities **Array*** : entes ativos, no formato do DTO `FederativeEntityCard`.
- *trashed **Array*** : entes na lixeira, no mesmo formato.
- *seals **Array*** : catálogo de selos habilitados (`SealOption`), repassado aos cards para a caixinha `+`.

### Importando componente

```php
<?php
$this->import('conectaente--entities-list');
?>
```

### Exemplo de uso

```html
<conectaente--entities-list :entities='<?= json_encode($cards) ?>' :trashed='<?= json_encode($trashedCards) ?>'></conectaente--entities-list>
```

### Observações

As abas seguem o `panel--entity-tabs` do core (`mc-tabs` com `sync-hash`, ícone de lixeira no cabeçalho da aba). A busca só age na aba de ativos e aceita o CNPJ com ou sem pontuação. As poucas regras próprias do card (corpo sem o `padding` e o `min-height` do core, quadro do selo alinhado à esquerda) ficam em `assets-src/sass/conectaente.scss`, compilado pelo mix.
