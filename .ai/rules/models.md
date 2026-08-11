---
paths:
  - 'app/Models/*.php'
---

# Models

## Date attributes are CarbonImmutable, not Illuminate\Support\Carbon
AppServiceProvider calls `Date::use(CarbonImmutable::class)`, so every `datetime` cast and `now()` returns `Carbon\CarbonImmutable`. Model `@property` docblocks must say `CarbonImmutable`; writing `Illuminate\Support\Carbon` is a type lie that PHPStan reports as `assign.propertyType` wherever the value is assigned back.

It also changes behaviour: `$model->some_date->addDay()` returns a new instance rather than mutating, so results must be assigned. `->copy()` is a harmless no-op on immutable dates.
