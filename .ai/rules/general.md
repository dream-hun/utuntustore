---
paths:
  - '**'
---

# General

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

## Deploying to production (Hostinger shared host)
Target: `ssh -p 65002 u166205141@145.223.89.19`, app root `~/domains/utuntutwubwenge.co.rw/public_html`, live at https://utuntutwubwenge.co.rw.

There is no git and no node/npm on that host, so deploys are **rsync from a local checkout**, never `git pull` on the server:

1. Locally: `git pull --ff-only`, then `npm run build` (`public/build` is gitignored and must be built here — the server cannot build it).
2. `rsync -az --delete-after -e "ssh -p 65002"` each changed path. Sync whatever the diff touched — `app/`, `routes/`, `database/seeders/`, `resources/`, `tests/`, `.ai/` — and always `public/build/`. Add `vendor/` only when `composer.lock` changed.
3. Never rsync `.env` or `storage/` — storage holds uploaded media and logs.
4. On the server: `php artisan optimize:clear && php artisan optimize`.

Two host quirks that break things if forgotten:

- The whole project lives in `public_html`, which is itself the Apache docroot. The root `.htaccess` rewrites every request into `public/`; without it `/` returns 403 and `.env` would be web-reachable.
- `public/storage` is a hand-made `ln -s ../storage/app/public`. PHP's `symlink()` is in this host's `disable_functions`, so `php artisan storage:link` cannot recreate it — if the link is lost, restore it with `ln -s` over SSH.
- Hostinger's CDN (`hcdn`) caches `/build/assets/*` for 7 days but serves HTML as `DYNAMIC`/`no-cache`. Content-hashed filenames make that safe, so no purge is needed; superseded chunks keep returning 200 from the edge for a while even though they are gone from disk. Verify a deploy by checking the hashes in the served HTML, not by expecting the old asset to 404.

Do not run `php artisan db:seed` on production. Reference data (5 provinces / 30 districts / 416 sectors), settings and the admin are already seeded; an empty catalog is intended, since demo products live in the separately-invoked `DemoDataSeeder`.

## Deploy: production vendor/ is --no-dev, and three paths must never be rsynced wholesale
Production's `vendor/` is a `composer install --no-dev --optimize-autoloader` tree. Rsyncing the local dev `vendor/` would push pest/faker/rector to the live host, so when `composer.lock` changes, build the prod tree first: run `composer install --no-dev --optimize-autoloader` in place, rsync `vendor/`, then `composer install` to restore dev deps. `COMPOSER_VENDOR_DIR=<scratch>` does NOT work as a way to keep the dev tree — lerd's composer shim does not forward the env var into the container, so it silently rewrites the real `vendor/` instead.

Three paths that break production if synced whole, so name subdirectories instead:
- `public/` — `public/hot` (Vite dev-server marker) and `fonts-manifest.dev.json` exist locally; `hot` would point the Vite helper at a dead dev server and blank the site. Sync only `public/build/`.
- `database/` — `database/database.sqlite` is a local dev DB that is not on the host. Sync `database/migrations/` or `database/seeders/` explicitly, or exclude the sqlite file.
- `bootstrap/` — `bootstrap/ssr/` is built locally but the host has no node to run SSR, and `bootstrap/cache/*.php` is regenerated by `php artisan optimize` on the server anyway.

After a `vendor/` sync, run `php artisan package:discover` on the host before `optimize:clear && optimize` — the host has no composer, so nothing else regenerates `bootstrap/cache/packages.php`.
