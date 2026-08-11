---
paths:
  - '**'
---

# General

## Pint: this project is not a git repo, so --dirty fails
`vendor/bin/pint --dirty` errors with "The [--dirty] option is only available when using Git." — the project directory has no .git.

Run `vendor/bin/pint --format agent` for everything, or pass explicit paths: `vendor/bin/pint app/Models tests/Feature --format agent`.

## Narrow mixed values instead of blind-casting — the codebase is PHPStan level max clean
`phpstan.neon` is level max with no baseline and currently reports zero errors. Keep it there; do not add ignores or baseline entries.

Values arriving from outside PHP's type system are `mixed`, and `(int) $x` on mixed is an error at this level. Use the established narrowing instead:
- Raw SQL aggregates / Faker / decoded JSON: `App\Support\Cast::int()` / `Cast::string()`.
- Config: `Config::integer('key')` / `Config::string('key')`, never `(int) config('key')`.
- Form Requests: `$this->integer('x')`, `$this->string('x')->toString()`, `$this->boolean('x')`, and `$this->filled('x') ? ... : null` for nullable columns — not `(int) $this->validated('x')`.

Run it with `vendor/bin/phpstan analyse --memory-limit=1G`; the 128M default crashes the worker.
