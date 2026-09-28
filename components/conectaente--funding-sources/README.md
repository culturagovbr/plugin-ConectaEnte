# Componente `<conectaente--funding-sources>`

Recursos de outras fontes do edital. É o porte do `opportunity-recursos-outras-fontes` do Pnab, sem salvamento próprio: tudo grava pelo "Salvar" da página.

### Propriedades

- *entity **Entity*** : a oportunidade.
- *prop **String*** : o metadado do bloco (`conectaente_fundingSources`).
- *classes **String|Array|Object*** : classes do contêiner.

### Comportamento

As fontes e o limite do nome vêm do vocabulário `FundingSource` e do serviço de saneamento, pelo jsObject (`config.conectaenteFundingSources`), publicado pelo `init.php`.

- **A pergunta nasce sem resposta**, e "Não" limpa o detalhamento.
- **Cada fonte marcada** abre o valor em reais; desmarcar volta a fonte para nulo, que é como a regra distingue "não usa" de "usa e vale zero".
- **"Recursos de outras fontes"** abre a lista livre: nome e valor por linha, com incluir e excluir. Só se inclui uma nova linha com todos os nomes preenchidos.
- O nome aceita **255 caracteres na tela**, o mesmo limite que o servidor aplica.
- O bloco grava `{houveUtilizacao, recursosProprios, conveniosParcerias, emendasParlamentares, remanescentesCiclo1, outrasFontes: [{nomeFonte, valor, _id}]}`. O `_id` é só da tela e não vai ao payload.

### O que difere do Pnab

O componente é cópia do original; o que mudou:

- **O metadado vem por `prop`**, em vez da chave fixa `recursosOutrasFontes`.
- **Não grava "não" ao montar** — o original respondia pelo administrador.
- **O aviso local "selecione pelo menos uma fonte" saiu**: essa regra é do servidor, e a mensagem dele aparece sob o bloco.
- **O subtexto da pergunta saiu**, porque citava a Política Aldir Blanc, que não é o contexto do plugin.
- **O nome da fonte é saneado nas duas pontas** — ver `Services/FundingSourceName.php` e a decisão "O nome da fonte de recurso é saneado nas duas pontas": `trim`, remoção de HTML e corte em 255, tanto no hook de `PATCH` que o CultEditais usa quanto no `save:before`, que cobre PUT, API e importação.
- **Os estilos vieram junto**, em `assets-src/sass/components/_funding-sources.scss`, com as cores em tokens do tema e `size()` convertido para `rem`.
