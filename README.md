# Contexto
Um plugin que permite a qualquer estado ou município publicar seus editais de fomento à
cultura e enviá-los automaticamente à Plataforma CultBR — o que hoje só funciona na
instalação do Ministério da Cultura.

# Instalação

O plugin não tem build. Os componentes ficam em `components/`, e o único estilo próprio é o `style.css` ao lado do componente, que o core carrega sozinho. O diretório `assets/` precisa existir — vem versionado com um `.gitkeep` — porque é a raiz que o core usa para resolver `../components/…`.


# Testes

A partir de `tests/` do repositório principal:

```bash
docker compose -f docker-compose.yml -f ../src/plugins/ConectaEnte/tests/docker-compose.yml \
  run --rm mapas pu /var/www/tests/ConectaEnte
```

Apontar sempre para `/var/www/tests/ConectaEnte`. Apontar para `/var/www/tests` rodaria a suíte do core sob a configuração do plugin.
