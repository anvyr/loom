# Coding Standards (Anvyr Loom)

This document defines the coding conventions for Anvyr Loom.

For contribution workflow, see [CONTRIBUTING.md](CONTRIBUTING.md).

## Baseline

- **PHP**: 8.4+
- **Strict types**: every PHP file should start with `declare(strict_types=1);`
- **Style**: PSR-12 as the baseline (spacing/brace placement/structure)
- **Typing**: type all parameters and return values; prefer typed properties
- **Avoid `mixed`** unless you are at a boundary (I/O, config, framework entrypoints) or there is a clear reason

If a rule is unclear in a specific case, prefer the simplest code that keeps invariants explicit.

## Comments

We aim for readable code with the least amount of comments.

### When to write a comment

Write comments only when they add information that **is not already obvious from the code**:

- **Why / intent**: rationale, constraints, tradeoffs (especially when the code is intentionally surprising)
- **Security invariants**: ordering requirements or trust boundaries
- **Compatibility/workarounds**: non-obvious behavior required due to upstream limitations
- **Tricky algorithms/edge cases**: parsing rules, regexes, caching formats, ordering guarantees

### When *not* to write a comment

- Don’t restate types already expressed by the signature (no “returns array of …” when `: array` exists).
- Don’t add docblocks that mirror `string|int|array` types already in code.
- Don’t leave commented-out code in the repository (delete it).

### Style

- Keep comments short and specific (prefer 1–3 lines).
- Prefer placing comments directly above the block they explain.
- Prefer changing code (naming/extraction) over adding explanation.

## PHPDoc

PHPDoc is allowed, but only when it adds information PHP cannot express clearly. We prioritize **Clean Code** over redundant documentation.

### The "Zero Noise" Policy

1.  **Interfaces are contracts**: Surface *meaningful* failure modes the caller is expected to handle (see [Throws on interfaces and contracts](#throws-on-interfaces-and-contracts)) - not every possible error PHP might emit.
2.  **Type Hints over DocBlocks**: Delete `@param` and `@return` tags if the type is fully expressed in the method signature.
    - *Exception*: Keep them for `array` content definition (e.g. `/** @return Page[] */`) or complex `mixed` types.
3.  **No "Echo" Comments**: Delete function descriptions that just translate the function name into English.
    - *Bad*: `/** Check if exists */ public function exists(...)`
    - *Good*: Delete the comment entirely.

### Throws on interfaces and contracts

Use `@throws` for outcomes that are **part of the API** - not found, validation failed, conflict, serialization refused—i.e. failures callers may catch or map at a boundary. Prefer the **narrowest** type that matches the promise (`NotFoundException` over a vague `RuntimeException` unless vagueness is intentional).

Omit `@throws` for programmer mistakes (`TypeError`, impossible internal state), unrecoverable environment failures callers won’t handle differently, or copy-pasting the same meaningless `Throwable` on every method. Use **method-level** tags when failure modes differ; a single **interface-level** note is enough when all methods share the same storage/IO contract.

Exhaustive per-method `@throws` is not required; documenting failure *shape* where it matters is.

Use PHPDoc for:
- **`@throws`**
- **Array shapes / collection types**
- **Deprecations** (`@deprecated`)

Avoid PHPDoc for:
- Redundant type repetition.
- "What" comments (use "Why" comments inside code instead).

## Tooling

We use automated tools to enforce style and catch issues early:

| Tool | Purpose | Command |
|------|---------|--------|
| **PHP-CS-Fixer** | Enforces PSR-12 and style rules | `composer cs:fix` |
| **PHPStan** | Static analysis (level 8) | `composer analyse` |

### Available Commands

```bash
composer cs:check   # Check style without fixing
composer cs:fix     # Auto-fix style issues
composer analyse    # Run static analysis
composer qa         # Run both checks
```

### Before Committing

Run `composer qa` to catch issues before they reach CI. The fixer will auto-correct most style violations.
