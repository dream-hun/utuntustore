<?php

declare(strict_types=1);

use App\Models\Order;
use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\BuildCheckoutQuote;
use App\Actions\Checkout\PlaceOrder;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\District;
use App\Models\Product;
use App\Models\Sector;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use App\Support\Checkout\CheckoutProblem;

/**
 * A coupon's usage limit is a promise to the vendor funding the discount: the
 * platform takes no commission, so every redemption past the limit is money out of
 * that shop's pocket that they never agreed to.
 *
 * Redemption is therefore claimed as a condition of the write, the same way stock is
 * locked before it is deducted.
 */
beforeEach(function (): void {
    $this->district = District::factory()->create();
    $this->sector = Sector::factory()->for($this->district)->create();

    $this->vendor = Vendor::factory()->sellable()->create();

    VendorDeliveryArea::factory()->create([
        'vendor_id' => $this->vendor->id,
        'district_id' => $this->district->id,
        'sector_id' => null,
        'delivery_fee' => 1000,
        'is_active' => true,
    ]);

    $this->product = Product::factory()->for($this->vendor)->published()->create([
        'price' => 10_000,
        'stock_quantity' => 100,
    ]);
});

/**
 * Place one order for the given customer, applying the coupon if one is given.
 */
function checkoutWith(User $customer, ?Coupon $coupon): Order
{
    $address = Address::factory()->for($customer)->create([
        'district_id' => test()->district->id,
        'sector_id' => test()->sector->id,
    ]);

    $cart = Cart::factory()->for($customer)->create();

    resolve(AddToCart::class)->handle($cart, test()->product);

    $quote = resolve(BuildCheckoutQuote::class)->handle($cart->fresh(), $address, $coupon);

    return resolve(PlaceOrder::class)->handle($customer, $cart, $quote);
}

it('records a redemption and increments the coupon once per order', function (): void {
    $coupon = Coupon::factory()->fixed()->create([
        'value' => 2000,
        'usage_limit' => 5,
        'used_count' => 0,
    ]);

    $order = checkoutWith(User::factory()->customer()->create(), $coupon);

    expect($order->discount)->toBe(2000)
        ->and($coupon->fresh()->used_count)->toBe(1)
        ->and($coupon->usages()->count())->toBe(1);
});

it('refuses to redeem a coupon past its usage limit', function (): void {
    // One use left, and it is taken between the quote being priced and the order
    // being written — exactly what a second customer checking out concurrently does.
    $coupon = Coupon::factory()->fixed()->create([
        'value' => 2000,
        'usage_limit' => 1,
        'used_count' => 0,
    ]);

    $customer = User::factory()->customer()->create();

    $address = Address::factory()->for($customer)->create([
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
    ]);

    $cart = Cart::factory()->for($customer)->create();
    resolve(AddToCart::class)->handle($cart, $this->product);

    $quote = resolve(BuildCheckoutQuote::class)->handle($cart->fresh(), $address, $coupon);

    expect($quote->discount)->toBe(2000);

    Coupon::query()->whereKey($coupon->id)->update(['used_count' => 1]);

    expect(fn (): Order => resolve(PlaceOrder::class)->handle($customer, $cart, $quote))
        ->toThrow(CheckoutException::class);

    expect($coupon->fresh()->used_count)->toBe(1)
        ->and($coupon->usages()->count())->toBe(0);
});

it('leaves no order behind when a coupon is exhausted mid-checkout', function (): void {
    $coupon = Coupon::factory()->fixed()->create([
        'value' => 2000,
        'usage_limit' => 1,
        'used_count' => 0,
    ]);

    $customer = User::factory()->customer()->create();

    $address = Address::factory()->for($customer)->create([
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
    ]);

    $cart = Cart::factory()->for($customer)->create();
    resolve(AddToCart::class)->handle($cart, $this->product);

    $quote = resolve(BuildCheckoutQuote::class)->handle($cart->fresh(), $address, $coupon);

    Coupon::query()->whereKey($coupon->id)->update(['used_count' => 1]);

    try {
        resolve(PlaceOrder::class)->handle($customer, $cart, $quote);
    } catch (CheckoutException $checkoutException) {
        expect($checkoutException->problems)->toContain(CheckoutProblem::CouponUnavailable);
    }

    // The whole transaction rolled back: no order, and the stock it would have
    // taken is still on the shelf.
    expect(Order::query()->count())->toBe(0)
        ->and($this->product->fresh()->stock_quantity)->toBe(100)
        ->and($cart->items()->count())->toBe(1);
});

it('does not touch the coupon when the cart carries none', function (): void {
    $coupon = Coupon::factory()->create(['usage_limit' => 5, 'used_count' => 0]);

    checkoutWith(User::factory()->customer()->create(), null);

    expect($coupon->fresh()->used_count)->toBe(0);
});
