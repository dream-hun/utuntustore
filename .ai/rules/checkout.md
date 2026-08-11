---
paths:
  - 'app/Actions/Checkout/**'
---

# Checkout

## Claim limited resources as a condition of the write, not with a prior check
Anything with a finite supply is claimed inside the PlaceOrder transaction, never checked-then-written. Stock is locked with `lockForUpdate()` in primary-key order and re-verified before decrementing. Coupons are claimed by putting the redeemable condition in the UPDATE itself: `Coupon::whereKey($id)->redeemable()->increment('used_count')`, and a return of 0 throws `CheckoutException::couponUnavailable()`.

`Coupon::isRedeemable()` (in-memory, for pricing a quote) and `Coupon::scopeRedeemable()` (SQL, for claiming) are the same rule expressed twice and must be kept in agreement — change one, change the other.

When a claim fails the whole order fails rather than repricing: every total the customer was shown already had the discount in it.
