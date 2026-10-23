# Componente `<conectaente--registration-channels>`

Formas de inscrição previstas no edital. É o porte do `opportunity-formas-inscricao-edital` do Pnab, sem salvamento próprio: tudo grava pelo "Salvar" da página.

### Propriedades

- *entity **Entity*** : a oportunidade.
- *prop **String*** : o metadado do bloco (`conectaente_registrationChannels`).
- *classes **String|Array|Object*** : classes do contêiner.

### Comportamento

Os seis canais vêm do vocabulário `RegistrationChannel` pelo jsObject (`config.conectaenteRegistrationChannels`), publicado pelo `init.php`. O valor gravado é o do contrato (`email`, `presencial`, `correio`, `oral`, `sistema`, `outros`); o rótulo exibido é o traduzido.

- **A pergunta nasce sem resposta**, e só o que o administrador escolher é gravado.
- **"Não" limpa as formas** já marcadas.
- **Cada canal marcado** ganha um campo de descrição; o de e-mail usa `type="email"` e o placeholder próprio.
- O bloco grava `{previstasNoEdital, formas: [{tipo, descricao}]}`.

### O que difere do Pnab

O componente é cópia do original; o que mudou:

- **O metadado vem por `prop`**, em vez da chave fixa `formasInscricaoEdital`.
- **O vocabulário é o do contrato**: seis canais, com `correio` e `oral` no lugar de `correspondencia` e `oralidade`, mais `sistema`, que não existe no Pnab.
- **Não grava "não" ao montar** — o original respondia pelo administrador; aqui a resposta é sempre dele.
- **A validação não é reimplementada em JS**: saíram os avisos locais de "selecione uma forma" e "preencha a descrição", que aqui vêm do servidor, e a chamada à rota `site/validaEmailFormasInscricao`, que é do tema.
- **O e-mail inválido aparece sob o campo** pela chave sintética `conectaente_registrationChannelsEmail`, que a regra devolve à parte do erro do bloco.
- **Os estilos vieram junto**, em `assets-src/sass/components/_registration-channels.scss`, com as cores em tokens do tema e `size()` convertido para `rem`.
