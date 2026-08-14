---
paths:
  - 'resources/js/**'
---

# Js

## shadcn add: use npm, not the detected pnpm
`npx shadcn@latest add ...` fails with `spawn pnpm ENOENT`. A `pnpm-workspace.yaml` exists at the root so shadcn picks pnpm, but pnpm is not installed and the project actually uses npm (package-lock.json).

Workaround: install the deps first with npm, then temporarily move `pnpm-workspace.yaml` aside, run the shadcn add, and move it back. Either delete the stale workspace file or install pnpm to fix this permanently.

## Paginated lists use DataTable — never wrap shadcn Table in your own overflow container
`components/ui/table.tsx` already wraps itself in `overflow-x-auto`. Adding `<div className="overflow-x-auto rounded-lg border">` around it nests two scroll regions, and neither is keyboard-focusable — a WCAG 2.1.1 failure that was repeated across 13 index pages.

Use `components/data-table.tsx` for any paginated list. It owns the scroll region (focusable via `useOverflowX` only when the table actually overflows), an sr-only `<caption>`, `scope="col"` headers, the loading/empty/rows branch and the paginator. `rows === undefined` means loading, so it drops straight onto a deferred Inertia prop; `empty` and `caption` are required props on purpose.

Pair it with `components/row-actions.tsx` (grouped menu; `rowLabel` is required so the trigger announces "Actions for Jane Doe" rather than 20 buttons all named "Actions") and `hooks/use-table-filters.ts` (debounced text / immediate selects, drops `page` on every filter change so filtering off page 5 cannot strand the reader).

Two traps when extending: conditional Tailwind classes must be literal map entries, never `hidden ${bp}:table-cell` — the scanner never sees a template string. And never randomise skeleton widths; this project builds an SSR bundle and random widths cause hydration mismatches.

All 13 index/detail tables are migrated — no page imports `components/ui/table` directly any more, and new ones should not either.

Extra props earned during that migration: `bordered={false}` when the table already sits inside a Card; `rowClassName` for per-row state (low stock, nested variant rows); `paginator` is typed `Paginated<unknown>` because vendor/inventory paginates products but renders a row per product *and* per variant. `RowAction` is a union — pass `href` for navigation so the item stays a real `<a>` and can be opened in a new tab, or `onSelect` for everything else, never both.
