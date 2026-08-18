---
paths:
  - 'database/seeders/**'
---

# Seeders

## Never use WithoutModelEvents in seeders — it breaks UUID generation
Every model uses the `App\Concerns\HasUuid` trait, which generates the public `uuid` in a `creating` model event. `Illuminate\Database\Console\Seeds\WithoutModelEvents` mutes that event, so every insert fails with:

`SQLSTATE[HY000]: General error: 1364 Field 'uuid' doesn't have a default value`

Do not add that trait to a seeder. If you need to bypass observers for performance, generate the uuid explicitly instead: `'uuid' => (string) Str::uuid()`.

## Never use a factory on the production seed path — fake() is require-dev
DatabaseSeeder and everything it `call()`s (RwandaLocationSeeder, SettingsSeeder) run on real deploys. Factories reach `UserFactory::definition()`, which calls `fake()` from `fakerphp/faker` — a require-dev package absent under `composer install --no-dev`. A `factory()` there is fatal in production with `Call to undefined function Database\Factories\fake()`, and it fails *after* the reference-data seeders commit, so the database looks half-seeded and has no admin account to log in with. This bit the utuntutwubwenge.co.rw deploy on 2026-08-18.

Write attributes out explicitly with `Model::query()->create([...])` instead — `Model::unguard()` is application-wide, and remember the columns the factory used to supply (for User: `status`, plus `role`, `email_verified_at`).

Faker is installed when the suite runs, so no ordinary test notices a regression. `tests/Feature/Database/ProductionSeedPathTest.php` guards it against the source, reading the seeder list off `DatabaseSeeder::run()` so a newly added production seeder is covered automatically. DemoDataSeeder uses factories freely and is fine — it is never called by DatabaseSeeder, which is exactly what keeps it safe.
