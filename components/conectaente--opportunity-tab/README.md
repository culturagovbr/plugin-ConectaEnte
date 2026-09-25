# Componente `<conectaente--opportunity-tab>`

Aba "CultBR" da página de edição de oportunidade. Só aparece na oportunidade selada por um Ente Federado. Reúne os campos do edital que o CultBR recebe e a lista dos campos pendentes.

O `mc-tab` fica dentro do componente porque o `v-if` depende de estado dele. A part `conectaente/opportunity-tab`, incluída no hook `template(opportunity.edit.tabs):end`, só instancia o componente.

### Propriedades

- *entity **Entity*** : a oportunidade da página.

### Quando a aba aparece

- **Pela conta local:** algum selo de `entity.seals` está entre os `federativeSealIds` publicados pelo `init.php`, isto é, selos de Ente Federado ativo. A lista vem de `FederativeEntitySealRepository::findSealIdsOfEnabledEntities()`, numa consulta só. Ao aplicar ou remover um selo, a aba reage sem recarregar.
- **Pelo servidor:** a rota `conectaente/opportunityRequirements` responde `sealed`. Essa resposta prevalece sobre a conta local até a oportunidade ganhar ou perder selo de Ente Federado. As duas contas podem divergir, por exemplo com selo de status -1, que não vem em `entity.seals`.

### Campos pendentes

A rota é consultada, sem cache do navegador:
- ao montar, a não ser que não exista nenhum Ente Federado ativo, caso em que nenhuma oportunidade é selada;
- quando a oportunidade ganha ou perde selo de Ente Federado;
- só na oportunidade selada:
  - quando `entity.__originalValues` muda, isto é, quando a entidade é repovoada pelo servidor (salvar, publicar, recarregar as fases);
  - quando o arquivo do regulamento ou o avatar muda. O avatar entra na validação do core quando a instalação o exige.

Detalhes do comportamento:
- **Por que não `__processing`:** ele também muda a cada renovação do lock (`renewInterval`, 45 s por padrão).
- **Consultas agrupadas:** mudanças que chegam juntas geram uma consulta só.
- **Consultas cruzadas:** agendar uma consulta invalida a que está em curso, e só vale a resposta da última.

O card mostra:
- "carregando" até a primeira resposta;
- a mensagem de falha, se a consulta falha;
- "Nenhum campo pendente." quando não há pendência.

O texto acompanha o status: no rascunho, os campos pendentes impedem a publicação; na oportunidade publicada, impedem o salvamento. A lista considera o que já foi salvo, não o que está sendo digitado.

### Campos

- `conectaente_executionType`.
- `conectaente_legalEntityTypes`, só quando "Pessoa Jurídica" está nos tipos de proponente. Usa `type="checklist"` para sair como caixas de seleção, porque metadado `multiselect` de entidade sai sempre como busca com lista suspensa.
- Os quatro multiselects (`conectaente--targeting-multiselect`).
- `conectaente_publishedAt`, com "obrigatório" só na oportunidade publicada: no rascunho, a data em branco é gravada ao publicar, e a data informada prevalece. Numa oportunidade publicada que ainda vai herdar o `publishedTimestamp`, o campo aparece vazio com "obrigatório", mas a lista não cobra a data, porque o próximo salvamento a herda.

Todos são gravados pelo "Salvar" da página, porque os metadados são da mesma entidade. Qualquer usuário que pode editar a oportunidade edita esses campos, inclusive a data de publicação.

### Importando componente

```php
<?php
$this->import('conectaente--opportunity-tab');
?>
```

### Exemplo de uso

```html
<conectaente--opportunity-tab :entity="entity"></conectaente--opportunity-tab>
```
