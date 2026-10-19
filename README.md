# ConectaEnte (Mapas Culturais v5)

Plugin que envia ao CultBR (Plataforma Conecta Ente) os editais publicados por entes federados,
conforme a obrigação da PNAB. Porte do plugin da v7 para a **v5** (PHP 7.2, Slim 2, Doctrine ORM 2.8,
Respect\Validation 1.x, PHPUnit 8).

Especificação: issues deste repositório. Plano do porte: `ConectaEnte/plan.md` no repositório principal.

## Ativação

Em `src/protected/application/conf/conf-base.d/plugins.php` (ou no `config.d` da instalação):

```php
'ConectaEnte' => ['namespace' => 'ConectaEnte'],
```

## Configuração

Cada chave vem de `$app->config['conectaente.<chave>']`, da variável de ambiente
`CONECTAENTE_<CHAVE>` ou do padrão:

| Chave | Variável | Padrão | Descrição |
|---|---|---|---|
| `mode` | `CONECTAENTE_MODE` | `dev` | `live` envia de verdade; qualquer outro valor é simulado |
| `host` | `CONECTAENTE_HOST` | vazio | Host da Conecta Ente (o contrato não declara `servers`) |

> Atenção: o padrão é **simulado**. Esquecer `CONECTAENTE_MODE=live` em produção faz o plugin simular
> em silêncio.

## Estrutura

```
Plugin.php        _init() (hooks, job) e register() (controller, metadados)
Config.php        leitura de configuração
Controllers/ Entities/ Http/Transport/ Jobs/ Metadata/ Payload/
Repositories/ Services/ Vocabulary/ views/ fixtures/ tests/
```

O autoload é o do core (`namespace ConectaEnte` → `plugins/ConectaEnte/`); não usa Composer.

## Restrições de código (PHP 7.2)

Sem `enum`, `readonly`, `match`, argumentos nomeados, propriedades tipadas, `?->`, `fn`
com tipo de retorno nem first-class callable. Verificação:

```bash
docker run --rm -v "$PWD":/app -w /app php:7.2-cli sh -c \
  'find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l'
```

## Testes

Unitários (PHPUnit 8, PHP 7.2 em container, sem banco nem HTTP). O `tests/run.sh` resolve os
caminhos sozinho, então funciona de qualquer diretório; só o caminho do script muda. Da raiz do
repositório principal (`v5mapas`):

```bash
src/protected/application/plugins/ConectaEnte/tests/run.sh                         # toda a suíte
src/protected/application/plugins/ConectaEnte/tests/run.sh --filter testEditalType # filtra por nome
```

Da raiz deste plugin:

```bash
tests/run.sh
tests/run.sh --filter testEditalType
```

Os testes ficam em `tests/src` (namespace `Tests\ConectaEnte`); `tests/bootstrap.php` carrega o autoload.
