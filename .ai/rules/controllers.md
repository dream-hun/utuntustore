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

## A closure prop is not lazy — compute expensive props inside one, never in the action body
Inertia resolves every plain closure prop on every request. Only `Inertia::optional()` and `Inertia::defer()` (the `IgnoreFirstLoad` types) are skipped on a first load, and on a *partial* reload the props filter runs before `resolveValue` — so a closure prop the request did not ask for is never invoked.

The consequence: a partial reload still executes your whole controller action. Anything computed into a local before `Inertia::render()` is paid for and then discarded. Building the catalog paginator eagerly meant every cart-drawer open on /shop ran a COUNT, the paginated SELECT and both eager loads for nothing — four queries, twice per quantity tap.

So: expensive props (paginators, aggregates, media-heavy shapes) go *inside* the closure. Cheap indexed lookups needed by more than one prop can stay in the body. Guarded by tests/Feature/Storefront/CatalogPerformanceTest.php.

## Never memoise per-request state on a controller property
`Route::getController()` caches the controller instance on the Route object, and the RouteCollection outlives a single request. A property set during one request is still there for the next one served by the same process — visible today in feature tests that make two requests, and in production under any persistent runtime.

Memoising a slug lookup on `$this` inside CatalogController leaked one visitor's vendor filter into the next request and failed CouponAllocationTest. Use a local variable captured by the prop closures instead; it is per-invocation and cannot leak.
