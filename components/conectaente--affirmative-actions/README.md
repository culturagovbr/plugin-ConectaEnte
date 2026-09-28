# Componente `<conectaente--affirmative-actions>`

Outras modalidades de ações afirmativas. É o porte do `opportunity-outras-modalidades-acoes-afirmativas` do Pnab, sem salvamento próprio: tudo grava pelo "Salvar" da página.

### Propriedades

- *entity **Entity*** : a oportunidade.
- *prop **String*** : o metadado do bloco (`conectaente_affirmativeActions`).
- *classes **String|Array|Object*** : classes do contêiner.

### Comportamento

As opções, as subcategorias e o limite da descrição vêm dos vocabulários `AffirmativeAction` e `AffirmativeActionGroup` pelo jsObject (`config.conectaenteAffirmativeActions`), publicado pelo `init.php`.

- **Nada vem marcado**, e só o que o administrador escolher é gravado.
- **"Não são previstas"** é exclusiva: limpa as demais, esvazia as subcategorias e a descrição, e desabilita as outras opções.
- **Opções com subcategoria** (bônus de agentes, bônus de temáticas, categoria específica e edital específico) abrem um multiselect próprio, gravado na chave da opção.
- **"Outra ação afirmativa prevista em legislação local"** abre a descrição, com contador e limite vindos da regra de publicação.
- O bloco grava `{opcoes, <opção com subcategoria>: [...], outra_legislacao_descricao}`.

### O que difere do Pnab

O componente é cópia do original; o que mudou:

- **O metadado vem por `prop`**, em vez da chave fixa `outrasModalidadesAcoesAfirmativas`.
- **As opções vêm do vocabulário do plugin**, não de uma lista no componente com fallback embutido; quais têm subcategoria é o próprio vocabulário que diz (`hasGroups`).
- **O limite da descrição vem da regra** (`PublicationRequirements::OTHER_LEGISLATION_MAX_LENGTH`), em vez de uma constante repetida no componente.
- **Não grava nada ao montar** — o original criava a estrutura na tela antes de o administrador responder.
- **As mensagens são as do servidor**: saíram os três textos de erro que o original repetia em JS. O cliente só destaca o campo; quem diz o que falta é a regra, que agora **nomeia a ação afirmativa** na cobrança de subcategoria.
- **O título é o do card**, e o componente não o repete.
- **Os estilos vieram junto**, em `assets-src/sass/components/_affirmative-actions.scss`, com as cores em tokens do tema e `size()` convertido para `rem`.
