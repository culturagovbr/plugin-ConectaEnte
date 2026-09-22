# Componente `<conectaente--par-selector>`

Seleção em cascata do PAR (Exercício → Meta → Ação → Atividade) de uma oportunidade, quando ela tem um selo de Ente Federado vinculado. Fica no card "Informações" da oportunidade, injetado via hook (issue #24).

### Propriedades

- *entity **Object*** : a oportunidade sendo editada. Lê `entity.parExercicioId`/`parMetaId`/`parAcaoId`/`parAtividadeId` para o estado inicial, e grava neles ao completar a seleção.

### Importando componente

```php
<?php
$this->import('conectaente--par-selector');
?>
```

### Exemplo de uso

```html
<conectaente--par-selector :entity="entity"></conectaente--par-selector>
```

### Observações

A árvore vem de `GET conectaente/parInformation/{opportunityId}`, resolvida no servidor a partir do selo de Ente Federado vinculado à oportunidade — não de uma entidade global de sessão, então duas oportunidades de entes diferentes mostram árvores diferentes. Quando a oportunidade não tem selo, ou o ambiente não expõe este dado (a API de produção pode não ter `par-information`), a resposta vem com `exercicios: []` e o componente inteiro não renderiza nada — não é tratado como erro.

Quando a árvore existe mas nenhum dos quatro níveis foi escolhido ainda, aparece um `mc-alert type="danger"` avisando que os dados do PAR não foram preenchidos.

Escolher um nível zera os níveis abaixo dele (trocar o Exercício limpa Meta/Ação/Atividade). A seleção só é salva (via `entity.save()`) quando os quatro níveis estão preenchidos — não existe salvar parcial nem botão de salvar separado, mesmo padrão de autosave que o resto da página de edição usa. Os quatro ids vão exatamente como escolhidos, sem casamento por nome e sem conceito de "modelo oficial" — fora de escopo desta issue.
