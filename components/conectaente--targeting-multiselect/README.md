# Componente `<conectaente--targeting-multiselect>`

Multiselect dos campos de direcionamento do edital: segmento, etapa, pauta e território. É o porte do `custom-mc-multiselect` do Pnab, sem salvamento próprio: tudo grava pelo "Salvar" da página.

### Propriedades

- *entity **Entity*** : a oportunidade.
- *prop **String*** : o metadado multiselect.
- *otherProp **String** = null* : o metadado "especificar". O campo aparece quando a opção `otherOption` está marcada.
- *otherOption **String** = null* : a chave da opção "Outros"/"Outra" do vocabulário.
- *allOptions **Boolean** = false* : mostra a caixa "Todas as opções". Hoje só o segmento a usa.
- *required **Boolean** = false* : mostra o "obrigatório" no título.
- *classes **String|Array|Object*** : classes do contêiner.

### Comportamento

As chaves sintéticas "não se direciona" e "todas as opções" vêm do jsObject (`config.conectaenteTargetingMultiselect`), publicado pelo `init.php` a partir de `TargetingOption`.

- **"Não se direciona"** grava só a chave dela, esconde a busca e limpa o "especificar".
- **"Todas as opções"** grava a chave sintética e todas as opções, **menos a de "Outros"**, e limpa o "especificar".
- **Tirar uma opção**, pela tag ou pelo dropdown, desmarca "Todas as opções" se alguma opção coberta por ela ficou de fora. Tirar "Outros" não desmarca, porque "Outros" não faz parte do que "Todas as opções" cobre.
- **Tirar "Outros"**, pela tag ou pelo dropdown, limpa o "especificar".

Os erros do servidor aparecem sob o campo, a partir de `entity.__validationErrors[prop]`.

O metadado sem valor chega vazio (`null` ou ausente). O `mc-multiselect` põe a chave escolhida no array que recebeu; enquanto o metadado não tem lista, esse array é avulso, e a lista é criada na primeira escolha (`@selected`). Assim, olhar a aba ou focar a busca não marca a página como alterada.

O título do campo é ligado ao grupo de controles por `aria-labelledby`, para leitor de tela.

### Importando componente

```php
<?php
$this->import('conectaente--targeting-multiselect');
?>
```

### Exemplo de uso

```html
<conectaente--targeting-multiselect :entity="entity" prop="conectaente_segments" other-prop="conectaente_segmentsOther" other-option="Outros" all-options required></conectaente--targeting-multiselect>
```
