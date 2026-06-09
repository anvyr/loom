# Upgrade Guide

This document covers **breaking changes** and required actions when upgrading between versions. For new features and improvements, see the [release notes](https://github.com/anvyr/loom/releases).

## 2.2.0

Version 2.2 is a **dependency injection and architecture** update. Static singletons and global state are replaced by container-managed instances across the board. The module contract becomes fully declarative, pages gain stable identity, and the runtime dependency footprint shrinks.

### Breaking changes

#### Static singletons removed

Several classes that previously used static `getInstance()` / `instance()` patterns are now resolved through the service container.

**Impact:**
- `ConfigRepository::getInstance()` and `ConfigRepository::setInstance()` no longer exist.
- `VersionRegistry::instance()` no longer exists.
- Code calling these static accessors will get a fatal error.

**Action required:**
- Replace `ConfigRepository::getInstance()` with `app(ConfigRepository::class)`.
- Replace `VersionRegistry::instance()` with `app(VersionRegistry::class)`.

#### TenancyManager is no longer static

All `TenancyManager` methods changed from `static` to instance methods. Tenancy state is now held by the `TenancyState` class.

**Impact:**
- Static calls like `TenancyManager::currentId()`, `TenancyManager::isEnabled()`, `TenancyManager::config()`, and `TenancyManager::bootstrapFromRequest()` will fatal.

**Action required:**
- Resolve the manager from the container: `app(TenancyManager::class)->currentId()`.
- For direct state access, use `app(TenancyState::class)->currentId()`, `->isEnabled()`, etc.

#### AssetServer is no longer static

`AssetServer` is now a `final` instance class registered as a container singleton.

**Impact:**
- `AssetServer::serve()`, `AssetServer::module()`, `AssetServer::init()` no longer exist as static methods.
- `init()` renamed to `initialize()`, `module()` renamed to `registerModule()`, `getModulePaths()` removed.

**Action required:**
- Resolve from the container: `app(AssetServer::class)->serve(...)`.
- Update renamed methods: `init()` → `initialize()`, `module()` → `registerModule()`.

#### Routes and views are now declarative

Convention-based auto-discovery of `routes/web.php`, `routes/api.php`, and `resources/views/` is removed. Modules must declare these in `module.json`:

```json
{
    "routes": {
        "web": "routes/web.php",
        "api": "routes/api.php"
    },
    "views": "resources/views"
}
```

`api` routes are automatically grouped under the `/api` prefix.

Route files must now return an explicit registrar closure:

```php
<?php

declare(strict_types=1);

use Anvyr Loom\Core\Application;
use Anvyr Loom\Http\Routing\Router;

return static function (Router $router, Application $app): void {
    $router->get('/docs', [DocsController::class, 'index']);
};
```

**Impact:**
- `BaseModule::loadRoutesFrom()` is removed.
- The `extra.autoload.routes` opt-out key is no longer recognized.
- Module manifest `extra` field semantics changed: previously all unknown top-level keys in `module.json` were collected into `extra`; now only an explicit `"extra"` key is read. Custom top-level keys outside `"extra"` will be silently lost.
- `BaseModule::loadViewsFrom()` still exists, but declarative view manifests are the default path.

**Action required:**
- Add `routes` and `views` keys to every `module.json`.
- Convert route files to return a registrar closure.
- Move any custom top-level keys in `module.json` under `"extra": { ... }`.

#### UUIDv7 page identity

Pages now carry a stable `id` (UUIDv7) in frontmatter alongside the `slug`. The page indexer automatically backfills `id` into existing content files that lack one.

**Impact:**
- `PageIndex` interface adds `getById()`. All custom implementations must add this method.
- `SqlitePageIndex` constructor changed from `string $path` to `Connection $connection`.
- `FileDriver::validatePage()` rejects pages without a valid UUID `id`.
- Content files without `id:` in frontmatter will be mutated by the indexer on the next index operation.
- `PageService` constructor gains `PageIndex` and `ConfigRepository` parameters.

**Action required:**
- Custom `PageIndex` implementations must add `getById(string $id): ?PageIndexEntry`.
- Code instantiating `SqlitePageIndex` directly must pass a `Connection` instance.
- Code instantiating `PageService` directly must pass the additional parameters.
- If your content files are under version control, expect a one-time diff when the indexer backfills `id` fields.

#### Route caching

`route:cache` now collects routes from all sources (modules + defaults).

**Impact:**
- Only `[Controller::class, 'method']` handlers are cacheable.
- Closure-based routes make `route:cache` fail fast with a clear error.

**Action required:**
- Convert any closure route handlers to controller references before running `route:cache`.

#### PHP migrations now receive a schema instance

The static `Schema` runtime owner is removed. `Schema::create()`, `Schema::drop()`, and `Schema::dropIfExists()` are now instance methods. PHP migrations receive a `Schema` instance explicitly.

**Impact:**
- Zero-argument migration methods are no longer the supported first-party contract.
- Migration bodies should use the injected schema instance instead of static schema calls.

**Action required:**
- Change `up(): void` to `up(Schema $schema): void`.
- Change `down(): void` to `down(Schema $schema): void` when present.
- Replace `Schema::create(...)`, `Schema::drop(...)`, and `Schema::dropIfExists(...)` with `$schema->create(...)`, `$schema->drop(...)`, and `$schema->dropIfExists(...)` inside PHP migrations.

#### Validator static extension API removed

`Validator::extend()` and `Validator::hasExtension()` static methods are removed. Custom validation rules must be registered through the container-managed `ValidationExtensionRegistry`.

**Impact:**
- Code calling `Validator::extend('rule', ...)` will fatal.

**Action required:**
- Replace `Validator::extend('rule', $callback)` with `app(ValidationExtensionRegistry::class)->extend('rule', $callback)`.

#### ContentParser constructor signature changed

`ContentParser` constructor gains a `ConfigRepository` parameter.

**Impact:**
- Code instantiating `ContentParser` directly must pass the additional parameter.

**Action required:**
- Pass `app(ConfigRepository::class)` when constructing `ContentParser` manually, or resolve it through the container.

#### `symfony/console` removed from production dependencies

`symfony/console` moved from `require` to `require-dev`. It remains available for the CLI tooling but is no longer installed for production HTTP deployments.

**Impact:**
- `composer install --no-dev` deployments that relied on `symfony/console` classes transitively will get class-not-found errors.

**Action required:**
- If your code uses Symfony Console classes at runtime, add `symfony/console` to your own `require` section.

#### Minor breaking changes

- `api_version` key removed from `config/version.php`. Code reading `config('version.core.api_version')` will get `null`.
- `request()` helper changed from a function-scoped `static` variable to the service container. Behavioral difference is minimal but may surface in testing or long-running processes.

## 2.1.0

Version 2.1 is a **modules, correctness, and polishing** update. The module system becomes convention-driven, global state is eliminated, and legacy PHP patterns are modernized.

### Breaking changes

#### Configuration namespace separator changed from dot to colon

Config keys for module-scoped configuration now use a colon (`:`) as the namespace separator instead of a dot (`.`).

**Impact:**
- All module-scoped config access must change from dot-only to colon-namespaced syntax.

**Action required:**
- Update all config calls: `config('loom.editor.enabled')` → `config('loom:editor.enabled')`.
- The format is `namespace:file.key` (colon separates the module namespace from the file-level dotted path).

#### `BaseModule::mergeConfigFrom()` removed

Module config registration is now handled automatically by `ModuleManager`. The manual `mergeConfigFrom()` escape hatch has been removed.

**Impact:**
- Modules that called `$this->mergeConfigFrom()` in `register()` will error.

**Action required:**
- Remove all `mergeConfigFrom()` calls from module `register()` methods.
- Place config files in `config/` within your module root. `ModuleManager` auto-registers them under the module's namespace.

#### Exception interfaces removed

Three interfaces have been deleted:
- `Anvyr Loom\Exceptions\ExceptionHandlerInterface`
- `Anvyr Loom\Exceptions\RenderableExceptionInterface`
- `Anvyr Loom\Exceptions\ReportableExceptionInterface`

**Impact:**
- Code referencing these interfaces will error.
- `Handler` no longer implements `ExceptionHandlerInterface`.
- `HttpException` no longer implements `RenderableExceptionInterface`.
- Custom exception renderers registered via `Handler::addRenderer()` now correctly take priority over `HttpException::toResponse()` (previously the interface check short-circuited before custom renderers could fire).

**Action required:**
- Replace `ExceptionHandlerInterface` type-hints with `Anvyr Loom\Exceptions\Handler`.
- Remove any `implements RenderableExceptionInterface` or `implements ReportableExceptionInterface` from custom exceptions.
- If you had a custom `ReportableExceptionInterface` implementation, register equivalent behavior via `Handler::addReporter()` instead.

#### `module:migrate-artifacts` renamed to `module:provision`

**Impact:**
- Scripts or CI pipelines referencing `loom module:migrate-artifacts` will fail.

**Action required:**
- Replace `module:migrate-artifacts` with `module:provision` in all scripts and documentation.

### Migration notes

#### Module commands are now declarative

Commands can be declared in `module.json` under the `commands` key instead of registering them imperatively:

```json
{
    "commands": {
        "command:name": "Namespace\\CommandClass"
    }
}
```

**Impact:**
- No breaking change. Imperative registration via the `commands.registering` event still works.

**Action required:**
- No action required. Consider migrating to declarative commands for reduced boilerplate.

#### Convention-based auto-loading

`ModuleManager` now auto-registers:
- **Config:** files in `config/` → namespaced under module name
- **Views:** files in `resources/views/` → namespaced as `modulename::path`
- **Routes:** `routes/web.php` and `routes/api.php` → auto-loaded at boot

Modules can opt out of route auto-loading via `module.json`:
```json
{
    "extra": {
        "autoload": {
            "routes": false
        }
    }
}
```

**Action required:**
- No action required in 2.1. If you are targeting 2.2+, route files must use the explicit registrar closure shown above; `loadViewsFrom()` remains optional and redundant when your manifest already declares `views`.

## 2.0.0

Version 2.0 marks the initial maturity milestone for Anvyr Loom. Every subsystem (routing, DI, views, content, caching, scheduling, CLI, database, tenancy) is now considered stable. Starting with this release, Core follows **strict Semantic Versioning**.

### Breaking changes

#### Removed content drivers

Core page content is now file-native only.

**Impact:**
- `DBDriver`, `HybridDriver`, and `AutoDriver` have been removed from Core.
- `content:migrate` command has been removed.
- The `pages` table migration has been removed from Core.
- `config/content.php` no longer accepts a page-content driver switch.

**Action required:**
1. Remove any old page-content driver overrides from your config.
2. If you still store Core pages in the legacy `pages` table, export them before upgrading.
3. Use file-based pages (`.vlt` or `.md`) for Core page content going forward.

**Notes:**
- Database-backed editorial content belongs in Anvyr Loom CMS, not the Core page driver layer.
- The `ContentDriver` contract remains, but Core now ships only the file-native production implementation.

## 1.9.0

### Router pattern compilation hardening

Route static segments are now regex-escaped before parameter compilation. This fixes edge cases where literal regex characters (for example `.`) were treated as regex tokens.

**Impact:**
- Routes like `/api/v1.0/status` now match literally.
- If you intentionally relied on regex-like behavior in static route text, update those routes to use explicit parameters.

**Action required:**
- Review routes containing literal regex-like characters only if you previously depended on the old incorrect matching behavior.

### QueryBuilder identifier validation

QueryBuilder now validates table/column/operator inputs for standard builder methods (`table`, `where`, joins, grouping, ordering, insert/update/upsert paths).

**Impact:**
- Unsafe identifier strings that previously slipped through now throw `InvalidArgumentException`.
- Use `RawExpression` / `raw()` for intentionally complex SQL fragments.

**Action required:**
- Replace unsafe dynamic identifiers with validated names or explicit `RawExpression` usage where complex SQL is intentional.

### Module lifecycle ownership cleanup

Module bootstrapping is now single-owned by core provider flow (duplicate bootstrap path removed).

**Impact:**
- Prevents duplicate module load/register/boot execution.
- No config changes required.

**Action required:**
- No action required unless you were relying on duplicate lifecycle execution side effects.

### Trusted proxy support (opt-in)

Request host/scheme/client IP can now use forwarded headers when the source proxy is trusted.

**New config (in `config/http.php`):**
```php
'trusted_proxies' => [
    'enabled' => false,
    'proxies' => [],
    'headers' => [
        'for' => 'X-Forwarded-For',
        'proto' => 'X-Forwarded-Proto',
        'host' => 'X-Forwarded-Host',
    ],
],
```

**Action required (only if behind reverse proxy):**
1. Enable `trusted_proxies.enabled`.
2. Set explicit proxy IPs/CIDRs in `trusted_proxies.proxies`.
3. Keep default header names unless your proxy uses custom ones.

**Action required (if not behind reverse proxy):**
- No action required. Leave trusted proxies disabled.

### View string evaluation guard

`ViewEngine::compileString()` and `ViewEngine::safe()` are now controlled by configuration.

**New config (in `config/view.php`):**
```php
'allow_string_evaluation' => true,
```

**Impact:**
- Runtime string template evaluation is now explicitly configurable.

**Action required:**
- For stricter production posture, set `allow_string_evaluation` to `false` unless you explicitly need runtime string templates.

### WebCron hardening options

WebCron authorization now uses constant-time token checks and supports optional defense-in-depth controls.

**New config (in `config/app.php`):**
```php
'cron_enabled' => false,
'cron_token' => '',
'cron_signed_urls' => false,
'cron_allowed_ips' => [],
'cron_rate_limit' => [
    'enabled' => false,
    'attempts' => 60,
    'decay' => 60,
],
```

**Action required (if using `/system/cron`):**
1. Ensure `cron_token` is set.
2. Optionally restrict `cron_allowed_ips`.
3. Optionally enable `cron_signed_urls` and/or `cron_rate_limit`.

**Action required (if not using `/system/cron`):**
- No action required. Leave WebCron disabled.

## 1.7.0

### MiddlewareInterface namespace change

`MiddlewareInterface` moved from `Anvyr Loom\Http\Middleware` to `Anvyr Loom\Contracts`. Update your imports:

```php
// Before
use Anvyr Loom\Http\Middleware\MiddlewareInterface;

// After
use Anvyr Loom\Contracts\MiddlewareInterface;
```

**Impact:**
- Old imports stop resolving.

**Action required:**
- Update all `MiddlewareInterface` imports to `Anvyr\Loom\Contracts\MiddlewareInterface`.

### Module discovery path change

The module scan pattern in `config/modules.php` changed from `Anvyr Loom-*` to `Anvyr Loom*`. If you override the `paths` config, update your pattern accordingly.

**Impact:**
- Custom module discovery overrides using the old pattern may miss modules.

**Action required:**
- Update any overridden module scan patterns from `Anvyr Loom-*` to `Anvyr Loom*`.

## 1.5.0

### AutoDriver behavior change

AutoDriver no longer switched drivers at runtime. It evaluated once at boot and stayed on that driver for the entire request lifecycle.

**Impact:**
- Runtime storage-strategy switching stopped.
- Threshold-based driver selection became boot-time only.

**Action required:**
- If you are upgrading all the way to `2.0.0`, follow the `2.0.0` section instead. AutoDriver has now been removed entirely.

### Removed: `content:import`

`content:import` was removed from Core.

**Impact:**
- The old import command is no longer available.

**Action required:**
- No direct replacement remains in current Core. As of `2.0.0`, Core page content is file-native.

## 1.3.0

### Rate limiting rewrite

**Impact:**
- `ThrottleRequests` now requires `RateLimiter` instead of `CacheDriver` (container handles this automatically)
- Config keys `max_attempts`/`decay_minutes` removed - use `limiters` array instead

**New config:**
```php
'rate_limit' => [
    'enabled' => true,
    'default' => 'standard',
    'limiters' => [
        'standard' => ['attempts' => 60, 'decay' => 60, 'by' => 'ip'],
        'api' => ['attempts' => 120, 'decay' => 60, 'by' => 'ip'],
        'auth' => ['attempts' => 5, 'decay' => 60, 'by' => 'ip'],
        'strict' => ['attempts' => 10, 'decay' => 60, 'by' => 'ip'],
    ],
    'whitelist' => ['127.0.0.1', '::1'],
],
```

**Action required:**
- Replace the old flat rate-limit settings with the new `limiters` array structure.
