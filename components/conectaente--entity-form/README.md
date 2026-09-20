# Componente `<conectaente--entity-form>`

Modal de cadastro e edição de Ente Federado. Sem `entity`, cadastra: nome, selo e token. Com `entity`, edita aquele ente: troca o token. O selo de um ente já cadastrado é cadastrado ou removido na listagem.

### Propriedades

- *entity **Object*** : o ente a editar. Quando ausente, o modal é de cadastro.

### Importando componente

```php
<?php
$this->import('conectaente--entity-form');
?>
```

### Exemplo de uso

```html
<conectaente--entity-form></conectaente--entity-form>
<conectaente--entity-form :entity='…'></conectaente--entity-form>
```

### Observações

O selo é escolhido pelo `conectaente--seal-picker` sobre a store `useConectaEnteSealCatalog`, que a listagem semeia com o catálogo que veio com a página — sem requisição, e já sem os selos que os cards vincularam desde então. O token só vai do navegador para o servidor — nunca volta preenchido. Na edição, campo vazio mantém o que está gravado, e é por isso que o rótulo diz "Novo token".

O CNPJ não é digitado: vem da resposta de `validar-token`, e o servidor recusa o cadastro se o token não for aceito. A validação é só do servidor: os campos do cadastro seguem o `entity-field` do core (`label.field__title` com `span.required`, `.field.error` e `small.field__error`), e cada mensagem que o controller devolve aparece sob o seu campo — erro de CNPJ sob o token, de onde o CNPJ vem; chave sem campo vira toast. Editar o campo limpa o erro dele.
