---
paths:
  - 'app/Actions/**'
---

# Actions

## Aggregate/report queries use toBase()
`selectRaw('COUNT(*) as order_count, SUM(total) as collected')` on an Eloquent builder hydrates models with phantom attributes that match no column — PHPStan flags them as `property.notFound`, and the row is not really that entity.

Add `->toBase()` so the query returns plain result rows, type the map closure `fn (object $row)`, and narrow each column with `Cast::int()` / `Cast::string()`. See `BuildVendorSalesReport` and `BuildVendorDashboard`.
