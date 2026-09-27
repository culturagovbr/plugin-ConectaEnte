# Componente `<conectaente--quota-reservation>`

Reserva de vagas (cotas) do edital. É o porte do `opportunity-reserva-vagas-cotas` do Pnab, sem salvamento próprio: tudo grava pelo "Salvar" da página.

### Propriedades

- *entity **Entity*** : a oportunidade.
- *prop **String*** : o metadado do bloco (`conectaente_quotaReservation`).
- *required **Boolean** = false* : mostra o "obrigatório" no título.
- *classes **String|Array|Object*** : classes do contêiner.

### Comportamento

As três cotas legais e a ampla concorrência vêm do vocabulário `LegalQuota` pelo jsObject (`config.conectaenteQuotaReservation`), publicado pelo `init.php`.

- **As quatro linhas nascem ao montar**: as três cotas legais nas três primeiras posições, ampla concorrência sempre por último. Abrir a aba não persiste nada.
- **A ampla concorrência recebe o que sobra** do total de vagas: a cada alteração das outras linhas, quando o total muda e ao montar o bloco.
- **Cotas extras** entram entre as legais e a ampla, uma por vez, com confirmar e cancelar; só elas podem ser removidas.
- **"Não aplicável"** zera vagas e valor da linha e desabilita os dois campos. Informar vagas ou valor na ampla concorrência desmarca a opção dela.
- **Percentual** é calculado sobre o total de vagas da oportunidade e mostra `—` enquanto o total não é informado ou a linha está como não aplicável.

### O que difere do Pnab

O componente é cópia do original; o que mudou:

- **O metadado vem por `prop`**, em vez da chave fixa `reservaVagasCotas`.
- **O `label` gravado é o texto pt-br do vocabulário `LegalQuota`**, não o rótulo traduzido da tela: ele vai ao payload em `reserva_vagas_cotas`, e o valor não pode mudar com o idioma da instalação. A exibição traduz, por `displayLabel`.
- **O título é o rótulo do metadado registrado** e ganha o "obrigatório" da regra de publicação.
- **O bloco edita a raiz da oportunidade**, não a primeira fase, que é de onde o CultEditais lê.
- **A ampla concorrência acompanha o total de vagas.** No original o total ficava em outra tela e só o que se digitava nas cotas recalculava a ampla; aqui o campo está ao lado da tabela, então mudar o total ajusta a ampla na hora. O ajuste também roda ao montar, porque a aba destrói o bloco ao trocar de aba e o total pode ter mudado na aba de fases enquanto isso.
- **Os estilos vieram junto**, em `assets-src/sass/components/_quota-reservation.scss`: a estrutura é a do original; as cores fixas viraram tokens do tema e a função `size()`, que é do tema, virou o valor em `rem`. As colunas da tabela passaram a proporções porque o card da aba é mais estreito que a tela do CultEditais.

### Herdado do original, com ressalva

- **O destaque da linha com erro casa o texto da mensagem**, então só acende na de soma de vagas, que aponta a ampla concorrência; as outras mensagens da regra não nomeiam a cota, e nenhuma linha fica marcada. O erro aparece sempre abaixo da tabela.
- **O total em reais é formatado com `R$` fixo**, como no original, enquanto o campo de valor usa a moeda da instalação (`mc-currency-input`). Numa instalação com outra moeda, os dois divergem.
