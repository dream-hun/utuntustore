---
paths:
  - resources/css/app.css
---

# Css

## Storefront palette is scoped by overriding --color-*, not --primary
The shop pages use the joyful-commerce palette (teal primary, cream/ink/aqua/gold, 0.875rem radius, Plus Jakarta display face); the admin/vendor/account dashboards keep the neutral app theme. The switch is the `.storefront` wrapper class on StorefrontLayout.

Two traps if you edit that block:

1. Override `--color-primary`, `--radius-lg`, etc. — NOT `--primary`/`--radius`. `@theme` (non-inline) resolves `--color-primary: var(--primary)` once at `:root`, so re-declaring `--primary` on a nested element has no effect. Utilities read `var(--color-primary)`, which does inherit and can be overridden anywhere. Keep the block unlayered so it beats `@layer theme`.
2. The selector list must keep `body:has(.storefront)`. Radix portals dialogs, sheets, dropdowns and select menus into `<body>`, outside the layout wrapper; without the body match they render in the neutral palette mid-storefront.

`font-display` is family-only (from `--font-display`); use the `font-heading` utility when a non-heading element needs the full display treatment (family + 700 + -0.03em).
