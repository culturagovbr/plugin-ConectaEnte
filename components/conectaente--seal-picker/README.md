# Componente `<conectaente--seal-picker>`

Seletor de selo sobre uma lista que já veio com a página. É o popover do `select-entity` do core — mesmo markup, mesmas classes (`select-entity__form`, `select-entity__results`) — sem o `mc-entities`: filtra localmente, então não faz requisição nenhuma.

### Eventos

- **select** — emitido com o selo escolhido (`{id, name, files.avatar}`); o popover fecha em seguida.

### Propriedades

- *seals **Array*** : selos disponíveis, no formato do DTO `SealOption` (`id`, `name`, `files.avatar`). O servidor manda só os habilitados que nenhum Ente Federado usa (nem na lixeira); selo com validade vem, e é recusado no vínculo com a mensagem que diz como resolver.

### Slots

- **button** `{ toggle }` : o que abre o popover — a caixinha `+` do card ou o botão do formulário.

### Importando componente

```php
<?php
$this->import('conectaente--seal-picker');
?>
```

### Exemplo de uso

```html
<conectaente--seal-picker :seals="seals" @select="addSeal($event)">
    <template #button="{ toggle }">
        <div class="entity-seals__seals--addSeal" @click="toggle()"><mc-icon name="add"></mc-icon></div>
    </template>
</conectaente--seal-picker>
```

### Observações

Existe porque o `select-entity` do core busca na API ao nascer (`mc-entities.created()` → `refresh()`, e o `mc-popover` monta o conteúdo com `eager-mount`): numa listagem com dezenas de entes sem selo isso virava dezenas de requisições iguais ao carregar a página. Aqui o catálogo chega uma vez, pelo servidor (`availableSeals`), e some o "Carregar mais", que só existia pela paginação da API.
