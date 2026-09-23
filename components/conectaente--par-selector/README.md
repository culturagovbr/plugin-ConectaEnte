# Componente `<conectaente--par-selector>`

Seleção em cascata do PAR (Exercício → Meta → Ação → Atividade) de uma oportunidade, quando ela tem um selo de Ente Federado vinculado. Fica no card "Informações" da oportunidade, injetado via hook.

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

A árvore vem de `GET conectaente/parInformation/{opportunityId}`, resolvida no servidor a partir do selo de Ente Federado vinculado à oportunidade — não de uma entidade global de sessão, então duas oportunidades de entes diferentes mostram árvores diferentes. A leitura na requisição é só cache: quem atualiza esse cache é o `Jobs\ParInformationSyncJob`, a cada 30 minutos, fora do caminho da requisição do usuário.

A resposta sempre traz `available` (`boolean`) junto de `exercicios`:
- `available: false` — o cache ainda não tem nada para este ente (job nunca rodou com sucesso ainda). O componente mostra `mc-alert type="warning"` avisando que as opções do PAR estão indisponíveis no momento.
- `available: true, exercicios: []` — a oportunidade não tem selo, ou o ente realmente não tem dados de PAR (API respondeu vazio para o cnpj dele). O componente inteiro não renderiza nada — não é tratado como erro.
- `available: true, exercicios: [...]` — árvore normal. Se nenhum dos quatro níveis foi escolhido ainda, aparece um `mc-alert type="danger"` avisando que os dados do PAR não foram preenchidos.

Escolher um nível zera os níveis abaixo dele (trocar o Exercício limpa Meta/Ação/Atividade). A seleção só é salva (via `entity.save()`) quando os quatro níveis estão preenchidos — não existe salvar parcial nem botão de salvar separado, mesmo padrão de autosave que o resto da página de edição usa. Os quatro ids vão exatamente como escolhidos, sem casamento por nome e sem conceito de "modelo oficial" — fora de escopo desta issue.
