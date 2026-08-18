---
paths:
  - 'resources/js/components/**'
---

# Components

## Signed-in navigation lives in the sidebar, defined once in nav-items.ts
`components/nav-items.ts` is the single source for the admin / vendor / account menus. `navigationFor(role)` picks the section from `usePage().props.auth.user.role`, and `AppSidebar` renders it through `NavMain`. Add or reorder links there, not in a page.

Do not reintroduce per-area horizontal nav bars in page bodies. `AdminNav`, `VendorNav` and `AccountNav` were deleted: they duplicated the sidebar links on screen, and `AdminNav` was built from sidebar primitives (`SidebarGroup`/`SidebarMenuButton`), so rendering it in the content column also popped a tooltip on every item whenever the real sidebar was collapsed — `SidebarMenuButton` shows its tooltip when the shared `SidebarProvider` state is `collapsed`, regardless of where the button sits.

Mark `exact: true` on an entry whose href is a prefix of its siblings (`/admin`, `/vendor`, `/account`); everything else prefix-matches via `isCurrentOrParentUrl` so detail pages keep their section lit.

`NavFooter` and its starter-kit Repository/Documentation links are gone; the sidebar footer is `NavUser` only.

## Seed an edit modal from props and key it — never setDefaults() + reset()
Inertia v3's `useForm` `reset` is a `useCallback` closing over the `defaults` state of the current render, while `setDefaults` only schedules a state update. Calling them back to back in an effect therefore resets to the PREVIOUS defaults: the first edit opens a blank form and every later one shows the row edited before it. This was live on the admin category modal.

The pattern the rest of the codebase uses (address, review, product modals): seed `useForm` directly from the record prop, and have the page mount the modal only while open, keyed by row — `{editing ? <FormModal key={editing.id ?? 'new'} ... /> : null}`. Each open then gets a fresh form and no effect is needed.

Invisible to the PHP suite, which asserts Inertia props and never renders React.
