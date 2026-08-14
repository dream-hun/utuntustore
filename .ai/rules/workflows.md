---
paths:
  - '.github/workflows/**'
---

# Workflows

## CI must install a coverage driver — never coverage: none
`composer ci:check` chains into `test:ci`, which runs `pest --parallel --coverage --min=100`. That hard-requires a PHP coverage driver, so the `shivammathur/setup-php` step must set `coverage: xdebug` (or `pcov`). Setting `coverage: none` fails the job with `ERROR No code coverage driver is available.` after every other check has already passed — this bit us once.

We use `xdebug` rather than the faster `pcov` on purpose: the gate is exactly `--min=100`, and Xdebug is what developers run locally, so a green local run implies a green CI run. `pcov` can differ from Xdebug on a few line-coverage edge cases, which would fail the 100% gate only in CI. Switch to `pcov` only if the runtime becomes a real problem, and re-verify the total is still 100%.
