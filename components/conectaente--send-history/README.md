# Componente `<conectaente--send-history>`

Mostra o histórico de envio do edital ao CultBR em três níveis — envio, tentativa e conteúdo da troca —, lendo a rota `conectaente/sendHistory/{id}`.

### Propriedades

- *entity **Entity*** : a oportunidade.

### Comportamento

Busca a primeira página no `created`. O componente vive dentro da aba "Histórico de envio" do `<conectaente--opportunity-tab>`, que é uma `<mc-tab cache>`: o conteúdo só monta na primeira abertura — a tela de edição não paga a consulta de quem nunca abre a aba — e não é desmontado ao trocar de aba, então a paginação sobrevive à navegação.

O layout segue os prints da documentação: cada envio é um card com o ícone do desfecho, o uuid, o instante de início e quem disparou; aberto, mostra a conclusão e o edital no PAR, e uma tentativa por requisição ("Tentativa 1/3", status HTTP, duração). A tentativa traz "Enviado em" e "Endpoint", uma faixa com a falha quando não houve sucesso, e os blocos "Payload gerado" e "Resposta do servidor".

A resposta é um bloco só — status, cabeçalhos, corpo —, na ordem em que o servidor a devolveu: é nos cabeçalhos que está a pista quando o corpo não é JSON.

"Carregar mais" acumula páginas. Falha na primeira carga troca a lista por um alerta; falha ao paginar preserva o que já está em tela e só avisa — perder o que foi lido para dizer que a página seguinte não veio seria pior.

A mensagem de erro é a que a rota devolve. O envelope de erro do core traz `error: true`, e exibi-lo cru é como um aviso acaba dizendo apenas "true".

Copiar usa a área de transferência do navegador e, fora de contexto seguro — o ambiente servido por HTTP puro —, cai na seleção de um campo fora da tela.

### Importando componente

```php
<?php
$this->import('conectaente--send-history');
?>
```

### Exemplo de uso

```html
<conectaente--send-history :entity="entity"></conectaente--send-history>
```
