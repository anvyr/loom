# Anvyr Loom

<p align="center">
  <img src="https://anvyr.dev/assets/images/full_logo_dark.png" alt="Anvyr Loom Logo" width="600"/>
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-Apache%202.0-blue.svg" alt="License"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg" alt="PHP Version"></a>
  <a href="https://github.com/anvyr/loom/actions/workflows/tests.yml"><img src="https://github.com/anvyr/loom/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
</p>

---

## Why Anvyr Loom?

A PHP framework for developers who want full-stack capabilities without framework opacity. Every service binding, middleware, and routing decision is visible in code you own. We call this **Pragmatic Zero Magic**.

- **Explicit over Implicit**: Service bindings, routes, and middleware are declared directly - no hidden resolution.
- **No Facades**: Dependencies arrive through constructors and method signatures, not global static state.
- **Content First**: Page content is file-native with frontmatter and block modes, tuned for publishing workloads.
- **Modern & Stable**: We track current PHP releases aggressively, drop legacy detours early, and prefer clear upgrade paths over compatibility baggage.
- **Lean by Design**: Small runtime, short dependency graph, compact enough to inspect end to end.

---

## Features

### Content & ORM
- **File-Based Content** Pages as `.vlt` or `.md` files with YAML frontmatter. Versionable in Git, no database required.
- **ORM with Relationships** Models, eager/lazy loading, casts, soft deletes, scopes, model events, and pivot operations (`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`).
- **Query Builder** Fluent SQL abstraction with joins, pagination, upsert, and query caching. SQLite, MySQL, PostgreSQL.
- **Schema Builder & Migrations** Database-agnostic table definitions with versioned migration system.

### Multi-Tenancy
- **Host, Path & Callback Resolvers** Map domains, path segments, or custom logic to tenants.
- **Full Isolation** Content, views, storage, cache, sessions, and database all scope per tenant.
- **CLI Orchestration** `--tenant=<id>`, `--all-tenants`, and checkpoint-based batched migrations.

### Modules
- **Plugin Architecture** Manifest-driven with semver dependency resolution and topological load ordering.
- **Auto-Integration** Modules auto-register routes, views, configs, and CLI commands.

### HTTP
- **Router** Named routes, parameters (optional/wildcard), groups, middleware pipelines, caching.
- **Middleware** CSRF, sessions, rate limiting, ETag response caching, error handling.
- **Rate Limiting** Named limiters, dynamic callbacks, IP whitelisting.
- **View Engine** Blade-like syntax, layout inheritance, partials, namespace support.
- **HTTP Client** cURL-backed with retry, auth, and typed responses.

### System
- **Job Queue** Database-backed with retry, deduplication, and worker daemon.
- **Task Scheduler** Cron-style scheduling via CLI or web endpoint.
- **Validation** Standalone validator with 16 built-in rules and custom extensions.
- **CLI Suite** 20+ commands for scaffolding, migrations, modules, cache, and diagnostics.
- **Caching** Multi-driver (file, Redis, APCu) with tag-based invalidation.
- **Logging** PSR-3 compliant with daily rotation and auto-cleanup.
- **Security** Auto-escaped templates, CSRF tokens, prepared statements, path traversal protection.

---

## Requirements

- **PHP 8.4+** (+ extensions: `pdo`, `mbstring`, `json`, `openssl`)
- **Optional PHP extensions**: `curl` for the HTTP client, `apcu` for APCu cache, `redis` for Redis cache
- **Linux, macOS, or WSL2**
- Composer
- SQLite, MySQL, or PostgreSQL (optional)

---

## Quick Start

```bash
# Clone and install
git clone https://github.com/anvyr/loom.git
cd loom
./install.sh

# Start dev server
./loom serve
```

Visit `http://localhost:8000`

You can also run `install.sh` as a standalone bootstrapper; when it is outside a checkout it clones Anvyr Loom first, then continues setup.

---

## Development QA

Routine local checks use Composer:

```bash
composer qa
composer test
```

For a reproducible PHP 8.4/8.5 environment without changing your host PHP install, use Podman:

```bash
PHP_VERSION=8.4 podman compose run --rm test
PHP_VERSION=8.5 podman compose run --rm test
PHP_VERSION=8.5 podman compose run --rm test-external
```

---

## Documentation

Full documentation will be soon available.

---

## License

Anvyr Loom is licensed under the [**Apache License 2.0**](LICENSE).

---

## Contributing

Contributions welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

**Security Issues:** Email security@anvyr.dev (do not open public issues!)

---

## Credits

Built with ❤️ by [Anvyr](https://anvyr.dev).

See [composer.json](composer.json) for dependencies.