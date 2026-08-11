---
paths:
  - 'database/migrations/**'
---

# Migrations

## Tests run on SQLite — guard MySQL-only schema features
phpunit.xml runs the suite on in-memory SQLite while dev/prod are MySQL. MySQL-only schema features must be guarded or the whole suite fails at migration time.

Example from the products migration:

```php
if (Schema::getConnection()->getDriverName() === 'mysql') {
    Schema::table('products', function (Blueprint $table): void {
        $table->fullText(['name', 'short_description', 'description']);
    });
}
```

Anything reading that index (catalog search) needs a LIKE fallback so tests exercise a real path.
