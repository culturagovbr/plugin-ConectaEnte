# Componente `<conectaente--result-date>`

Campo de data com a aparência do `entity-field-datepicker` do core, para uma resposta que não é propriedade de entidade: aquele exige `entity` e `prop`, e grava `McDate`.

### Propriedades

- *modelValue **String** = null* : a data em `AAAA-MM-DD`.
- *inputId **String** = null* : o id do campo, para o rótulo.

### Eventos

- *update:modelValue* : a data em `AAAA-MM-DD`; nulo quando o campo é esvaziado. Ao sair do campo com a data pela metade, vai o texto digitado, para a validação recusar.

### Importando componente

```php
<?php
$this->import('conectaente--result-date');
?>
```

### Exemplo de uso

```html
<conectaente--result-date input-id="data" v-model="answer"></conectaente--result-date>
```
