---
paths:
  - 'database/seeders/**'
---

# Seeders

## Never use WithoutModelEvents in seeders — it breaks UUID generation
Every model uses the `App\Concerns\HasUuid` trait, which generates the public `uuid` in a `creating` model event. `Illuminate\Database\Console\Seeds\WithoutModelEvents` mutes that event, so every insert fails with:

`SQLSTATE[HY000]: General error: 1364 Field 'uuid' doesn't have a default value`

Do not add that trait to a seeder. If you need to bypass observers for performance, generate the uuid explicitly instead: `'uuid' => (string) Str::uuid()`.
