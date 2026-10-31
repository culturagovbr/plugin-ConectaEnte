# ConectaEnte (Mapas Culturais v5)

Plugin that sends the opportunities published by federated entities to CultBR (the Conecta Ente
platform), as required by the PNAB. Port of the v7 plugin to **v5** (PHP 7.2, Slim 2, Doctrine ORM 2.8,
Respect\Validation 1.x, PHPUnit 8).

Specification: this repository's issues. Porting plan: `ConectaEnte/plan.md` in the main repository.

## Activation

In `src/protected/application/conf/conf-base.d/plugins.php` (or in the installation's `config.d`):

```php
'ConectaEnte' => ['namespace' => 'ConectaEnte'],
```

## Configuration

Each key is read from `$app->config['conectaente.<key>']`, then from the environment variable
`CONECTAENTE_<KEY>`, then falls back to the default:

| Key | Variable | Default | Description |
|---|---|---|---|
| `mode` | `CONECTAENTE_MODE` | `dev` | `live` sends for real; any other value is simulated |
| `host` | `CONECTAENTE_HOST` | empty | Conecta Ente host (the contract does not declare `servers`) |

> Warning: the default is **simulated**. Forgetting `CONECTAENTE_MODE=live` in production makes the
> plugin simulate silently.

## Structure

```
Plugin.php        _init() (hooks, job) and register() (controller, metadata)
Config.php        configuration reader
Controllers/ Entities/ Http/Transport/ Jobs/ Listeners/ Metadata/ Payload/
Repositories/ Services/ Vocabulary/ views/ fixtures/ tests/
```

Autoloading is the core's (`namespace ConectaEnte` → `plugins/ConectaEnte/`); Composer is not used.

## Registration API (`/conecta-ente`)

Only `saasSuperAdmin` has access. Anonymous users are redirected to the login; any other user gets `403`.
Request bodies may be form-urlencoded or `application/json`.

| Action | Route | Fields |
|---|---|---|
| list | `GET /conecta-ente` or `GET /conecta-ente/list` | – |
| create | `POST /conecta-ente/create` | `name`, `seal_id`, `token` |
| verify token | `POST /conecta-ente/verifyToken` | `token` (verifies only, saves nothing; returns the suggested `name` and `cnpj`) |
| update | `PATCH /conecta-ente/update/<id>` | any of `name`, `seal_id`, `token` |
| remove | `DELETE /conecta-ente/remove/<id>` | – |

Responses carry `id`, `name`, `seal_id`, `cnpj`, `token_registered` and `token_last4`; the token itself
is never returned. Errors use `{"error": true, "data": ...}` with `400` (validation, rejected token),
`403`, `404`, `409` (seal already used by another entity, naming it) or `503` (CultBR unavailable or
`CONECTAENTE_HOST` missing in live mode).

## Admin screen

`saasSuperAdmin` sees an "Entes Federados" item in the panel menu, leading to `/painel/federative-entities`
(`views/panel/federative-entities.php`, assets in `assets/`). It lists the entities (name, seal name,
formatted CNPJ, creation date) and creates, edits and removes them through the API above; the token is
never shown, and a blank token field on edit keeps the current one. Other users get `403`. Outside
`CONECTAENTE_MODE=live` the screen shows a "simulated mode" banner.

## One entity per opportunity

An opportunity cannot hold the seals of two different federative entities, so the sending never has
to choose between tokens. Applying a second entity's seal is refused with the message naming both
entities; a seal that is not registered as an entity, and the same entity's seal again, pass. Pending
seal requests count as already applied. Only opportunities are checked (agents, spaces and projects
are not), and opportunities that already hold two entity seals are left alone.

Two layers enforce it (`Plugin::sealConflictMessage()`, rule in `Services/OpportunitySealGuard`):

- `Listeners/SingleEntityPerOpportunityListener`, a Doctrine `prePersist` listener, covers every code
  path that saves the relation and throws `BadRequest`. It is not the `save:before` entity hook: in v5
  only entities with `@ORM\HasLifecycleCallbacks` fire those, and `SealRelation` does not, so the hook
  would never run. The core does not turn `BadRequest` into a response.
- A `POST(opportunity.createSealRelation):before` hook, used by the seals screen of the opportunity,
  answers `400` with `{"error": true, "data": "<message>"}` (only for who may apply the seal).

Known limit: two administrators applying seals of different entities to the same opportunity at the
same instant can both pass the check, because each reads the database before the other's insert. The
check runs in `persist()`, before the `flush` transaction opens, so a row lock would not hold, and a
database constraint would mean changing `seal_relation`, a core table. Relations still waiting for a
`flush` in the same request are seen.

## Language convention

Everything in the plugin is in English: identifiers, database columns, request/response fields and
documentation. Only the texts shown to users (wrapped in `i::__()`) stay in Portuguese, and files in
`fixtures/` mirror the external CultBR contract and keep its field names as they are.

## Code restrictions (PHP 7.2)

No `enum`, `readonly`, `match`, named arguments, typed properties, `?->`, `fn` with a return type
or first-class callables. Check with:

```bash
docker run --rm -v "$PWD":/app -w /app php:7.2-cli sh -c \
  'find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l'
```

## Tests

Unit tests (PHPUnit 8, PHP 7.2 in a container, no database or HTTP). `tests/run.sh` resolves its paths
itself, so it works from any directory; only the script path changes. From the main repository root
(`v5mapas`):

```bash
src/protected/application/plugins/ConectaEnte/tests/run.sh                         # whole suite
src/protected/application/plugins/ConectaEnte/tests/run.sh --filter testEditalType # filter by name
```

From this plugin's root:

```bash
tests/run.sh
tests/run.sh --filter testEditalType
```

Tests live in `tests/src` (namespace `Tests\ConectaEnte`); `tests/bootstrap.php` loads the autoload.
