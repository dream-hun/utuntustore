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

## TIA replays stale coverage after edits: re-record the baseline or the 100% gate fails falsely
`composer test:coverage` runs `pest --parallel --tia --coverage --min=100`. TIA replays cached coverage for unaffected tests, and that cache is keyed to the *old line numbers*. After any edit that shifts lines, the gate fails with phantom uncovered lines in exactly the files you touched (and `@codeCoverageIgnore` is not honoured in a replay).

It is not a real coverage regression. Fix by re-recording the baseline, then re-run:

    ./vendor/bin/pest --parallel --tia --fresh   # or a plain `--tia` run
    composer test:coverage

On a fresh graph Pest deliberately disables TIA during a coverage run ("TIA is skipped as an active coverage report narrows the edges it could record") and reports true coverage. `composer test:ci` omits `--tia` for this reason — trust that one when in doubt.

## Keep short ternaries on one line — a split `: null` branch is uncoverable
php-code-coverage counts `: null` / `: []` on its own line as an executable statement, but PHP emits no opcode for a constant branch, so Xdebug can never record it. A multi-line ternary with a constant false branch is therefore permanently uncovered and blocks the `--min=100` gate, however many tests exercise it.

Write these on one line, which is what the rest of the codebase already does:

    'description' => $this->filled('description') ? $this->string('description')->toString() : null,

If the truthy branch is too long to fit, extract it to a private method rather than splitting the ternary (see `SecurityController::passkeys()`).
