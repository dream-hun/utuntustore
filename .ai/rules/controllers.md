---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Serialize entities through the Presents* traits, never inline per controller
Shared payload shapes live in traits, not hand-copied arrays. `App\Http\Controllers\Concerns\PresentsOrders` (order items, vendor-order totals, status transitions) and `PresentsAddresses` (addressProps / addressDetail / deliveryLabel) are used by the account, vendor, admin and storefront order screens. `Storefront\Concerns\PresentsCatalog` / `PresentsCheckout` cover product, vendor and quote shapes.

Where a screen needs extra fields, spread the shared shape and add them: `[...$this->orderItemLine($item), 'sku' => $item->sku]`. Do not re-declare the base keys.

These shapes were previously written out 4-5 times each and had already drifted into two incompatible address variants. Duplication here does not fail loudly, it just lets the customer, vendor and admin views of one sale disagree.

## Resolve the authenticated user through currentUser(), not $request->user()
`$request->user()` is `?User`, so passing it straight into an action typed against `User` is a type error. Controllers extend `App\Http\Controllers\Controller`, which provides `currentUser(Request $request): User` — it aborts 401 if a route ever reaches a controller without the `auth` middleware. Form Requests use the matching `App\Concerns\ResolvesAuthenticatedUser::authenticatedUser()`.

This matters most inside validation rules: `Rule::exists(...)->where('user_id', $this->user()?->id)` silently becomes `where('user_id', null)` for a guest, widening an ownership check without ever failing.

Hoist it to a local when used more than once, especially inside per-row closures.
