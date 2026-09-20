# Contexto
Um plugin que permite a qualquer estado ou município publicar seus editais de fomento à
cultura e enviá-los automaticamente à Plataforma CultBR — o que hoje só funciona na
instalação do Ministério da Cultura.

# Instalação

O plugin traz o SASS em `assets-src/` e **não versiona o CSS gerado**: instalar exige o build, a partir de `src/` do repositório principal.

```bash
pnpm install --filter @mapas/plugin-conectaente
cd plugins/ConectaEnte && pnpm run build
```

Sem isso o plugin funciona, mas a tela de entes federados aparece sem estilo. O estilo é registrado nos grupos `app` e `app-v2`, porque BaseV1 imprime um e BaseV2 o outro.

# Testes

A partir de `tests/` do repositório principal:

```bash
docker compose -f docker-compose.yml -f ../src/plugins/ConectaEnte/tests/docker-compose.yml \
  run --rm mapas pu /var/www/tests/ConectaEnte
```

Apontar sempre para `/var/www/tests/ConectaEnte`. Apontar para `/var/www/tests` rodaria a suíte do core sob a configuração do plugin.
