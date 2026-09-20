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

Revelar o token de um ente pede a **senha local** do administrador — o hash `localAuthenticationPassword`, o mesmo do MultipleLocalAuth. Conta sem senha local recebe a orientação de definir uma em Conta e Privacidade.

# Testes

A partir de `tests/` do repositório principal:

```bash
docker compose -f docker-compose.yml -f ../src/plugins/ConectaEnte/tests/docker-compose.yml \
  run --rm mapas pu /var/www/tests/ConectaEnte
```

Apontar sempre para `/var/www/tests/ConectaEnte`. Apontar para `/var/www/tests` rodaria a suíte do core sob a configuração do plugin.
