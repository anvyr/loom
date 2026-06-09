# Contributing to Anvyr Loom

Thanks for your interest in contributing to Anvyr Loom.

## Quick Start

1. **Clone and install**
   ```bash
   git clone https://github.com/anvyr/loom.git
   cd loom
   composer install
   ```

2. **Bootstrap**
   ```bash
   ./loom install
   ```

3. **Run tests**
   ```bash
   composer test
   ```

   For optional integrations and adapter coverage:
   ```bash
   composer test:external
   ```

4. **Run QA checks**
   ```bash
   composer qa
   ```

   To test against a specific PHP version without changing your host environment, use Podman:
   ```bash
   PHP_VERSION=8.4 podman compose run --rm test
   PHP_VERSION=8.5 podman compose run --rm test
   PHP_VERSION=8.5 podman compose run --rm test-external
   ```

5. **Serve locally**
   ```bash
   ./loom serve
   ```

## Code Standards

All code style and PHPDoc guidance lives in [CODING_STANDARDS.md](CODING_STANDARDS.md). Keep changes minimal and explicit, and follow the “Zero Noise” PHPDoc policy.

## Branching & Commits

- Branches: `feature/*`, `bugfix/*`, `hotfix/*`
- Commit messages: [Conventional Commits](https://www.conventionalcommits.org/)

## Pull Requests

1. Write focused changes with tests when possible.
2. Update docs when behavior changes.
3. Run `composer qa` before pushing (style + static analysis).

## AI Usage

See [Section V of the Anvyr Constitution](https://anvyr.dev/constitution#on-ai). You own every line you submit.

## Security

For security reports, see [SECURITY.md](SECURITY.md). Do not open public issues for vulnerabilities.

## License

Anvyr Loom is Apache-2.0. By contributing, you agree your contributions are licensed under Apache-2.0 (inbound = outbound).