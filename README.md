# Contexto
Um plugin que permite a qualquer estado ou município publicar seus editais de fomento à
cultura e enviá-los automaticamente à Plataforma CultBR — o que hoje só funciona na
instalação do Ministério da Cultura.

# Instalação

O estilo é SCSS em `assets-src/sass/`, compilado pelo `@mapas/scripts` como nos demais plugins, e o CSS gerado **não é versionado**: instalar exige o build, a partir de `src/` do repositório principal.

```bash
pnpm install --filter @mapas/plugin-conectaente
cd plugins/ConectaEnte && pnpm run build
```

Sem o build a tela de Entes Federados não abre: `assets/` é a raiz que o core usa para resolver os componentes. O estilo é registrado nos grupos `app` e `app-v2`, porque BaseV1 imprime um e BaseV2 o outro.

Revelar ou copiar o token, excluir, recuperar e excluir permanentemente pedem a **senha local** do administrador — o hash `localAuthenticationPassword`, o mesmo do MultipleLocalAuth. Conta sem senha local recebe a orientação de definir uma em Conta e Privacidade. A senha confirmada vale por uma **janela de 2 minutos** (fixa, contada da última digitação, presa à sessão e fechada no logout); `CONECTAENTE_PASSWORD_WINDOW` muda a duração em segundos, e `0` volta a pedir a senha a cada ação.

# Selo do Ente Federado

O selo cadastrado no Ente Federado deve ser criado **sem período de validade**: um selo com prazo expiraria a integração sem aviso. O plugin recusa o vínculo de selo com validade — a mensagem manda editar o selo e remover a validade — e, se um selo já vinculado ganhar validade depois, a listagem avisa. Só relação de selo concedida traz a oportunidade para a integração. A caixinha `+` e o cadastro só oferecem selos que nenhum Ente Federado usa — inclusive na lixeira, que continua ocupando o selo.

# Lixeira

Excluir um Ente Federado manda-o para a lixeira, no padrão do core (`status`): ele some da listagem e deixa de integrar, mas continua ocupando CNPJ e selo, e o token continua no banco. Recuperar devolve tudo; excluir permanentemente apaga ente, vínculo e token.

# Dumps

A tabela `conectaente_federative_entity` guarda o token de cada Ente Federado em texto claro. **Nunca inclua essa tabela em dump compartilhado** — todo dump com ela é um vazamento de credencial.

# Testes

A partir de `tests/` do repositório principal:

```bash
docker compose -f docker-compose.yml -f ../src/plugins/ConectaEnte/tests/docker-compose.yml \
  run --rm mapas pu /var/www/tests/ConectaEnte
```

Apontar sempre para `/var/www/tests/ConectaEnte`. Apontar para `/var/www/tests` rodaria a suíte do core sob a configuração do plugin.
