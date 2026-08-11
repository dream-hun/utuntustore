<?php

declare(strict_types=1);

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\BuildCheckoutQuote;
use App\Actions\Checkout\PlaceOrder;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Cart;
use App\Models\District;
use App\Models\Product;
use App\Models\Sector;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use App\Support\Checkout\CheckoutProblem;

/**
 * Checkout is where the marketplace's money rules are enforced. These tests assert
 * the invariants the business model depends on: the order splits per vendor, each
 * vendor's total is exactly what they collect at the door, and the platform never
 * takes a cut.
 */
beforeEach(function (): void {
    $this->district = District::factory()->create();
    $this->sector = Sector::factory()->for($this->district)->create();

    $this->customer = User::factory()->customer()->create();

    $this->address = Address::factory()->for($this->customer)->create([
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
    ]);

    $this->cart = Cart::factory()->for($this->customer)->create();
});

/**
 * Create a sellable vendor that delivers to the test address for a known fee.
 */
function vendorDeliveringHere(int $deliveryFee): Vendor
{
    $vendor = Vendor::factory()->sellable()->create();

    VendorDeliveryArea::factory()->create([
        'vendor_id' => $vendor->id,
        'district_id' => test()->district->id,
        'sector_id' => null,
        'delivery_fee' => $deliveryFee,
        'is_active' => true,
    ]);

    return $vendor;
}

function publishedProduct(Vendor $vendor, int $price, int $stock = 10): Product
{
    return Product::factory()->for($vendor)->published()->create([
        'price' => $price,
        'stock_quantity' => $stock,
    ]);
}

it('splits a multi-vendor cart into one vendor order per vendor', function (): void {
    $vendorA = vendorDeliveringHere(2000);
    $vendorB = vendorDeliveringHere(1500);

    $phone = publishedProduct($vendorA, 300_000);
    $case = publishedProduct($vendorA, 20_000);
    $shoes = publishedProduct($vendorB, 100_000);

    resolve(AddToCart::class)->handle($this->cart, $phone);
    resolve(AddToCart::class)->handle($this->cart, $case);
    resolve(AddToCart::class)->handle($this->cart, $shoes);

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh(), $this->address);
    $order = resolve(PlaceOrder::class)->handle($this->customer, $this->cart, $quote);

    expect($order->vendorOrders)->toHaveCount(2);

    $orderForA = $order->vendorOrders()->where('vendor_id', $vendorA->id)->sole();
    $orderForB = $order->vendorOrders()->where('vendor_id', $vendorB->id)->sole();

    // Each vendor order's total is exactly what that vendor collects in cash.
    expect($orderForA->subtotal)->toBe(320_000)
        ->and($orderForA->shipping_fee)->toBe(2000)
        ->and($orderForA->total)->toBe(322_000)
        ->and($orderForB->subtotal)->toBe(100_000)
        ->and($orderForB->shipping_fee)->toBe(1500)
        ->and($orderForB->total)->toBe(101_500);

    expect($order->subtotal)->toBe(420_000)
        ->and($order->total)->toBe(423_500);
});

it('keeps the order shipping fee equal to the sum of its vendor orders', function (): void {
    $vendorA = vendorDeliveringHere(2000);
    $vendorB = vendorDeliveringHere(1500);

    resolve(AddToCart::class)->handle($this->cart, publishedProduct($vendorA, 50_000));
    resolve(AddToCart::class)->handle($this->cart, publishedProduct($vendorB, 40_000));

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh(), $this->address);
    $order = resolve(PlaceOrder::class)->handle($this->customer, $this->cart, $quote);

    expect($order->shipping_fee)->toBe(
        (int) $order->vendorOrders()->sum('shipping_fee'),
    )->toBe(3500);
});

it('copies the resolved delivery fee onto the vendor order so later fee changes cannot rewrite history', function (): void {
    $vendor = vendorDeliveringHere(2000);

    resolve(AddToCart::class)->handle($this->cart, publishedProduct($vendor, 10_000));

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh(), $this->address);
    $order = resolve(PlaceOrder::class)->handle($this->customer, $this->cart, $quote);

    $vendor->deliveryAreas()->update(['delivery_fee' => 9999]);

    expect($order->vendorOrders()->sole()->shipping_fee)->toBe(2000);
});

it('snapshots the product name and price onto the order item', function (): void {
    $vendor = vendorDeliveringHere(0);
    $product = publishedProduct($vendor, 25_000);

    resolve(AddToCart::class)->handle($this->cart, $product, 2);

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh(), $this->address);
    $order = resolve(PlaceOrder::class)->handle($this->customer, $this->cart, $quote);

    $item = $order->items()->sole();
    $originalName = $product->name;

    expect($item->product_name)->toBe($originalName)
        ->and($item->unit_price)->toBe(25_000)
        ->and($item->quantity)->toBe(2)
        ->and($item->subtotal)->toBe(50_000);

    $product->update(['name' => 'Renamed', 'price' => 1]);

    // Historical product names and prices must not change when the catalog does.
    expect($item->fresh()->product_name)->toBe($originalName)
        ->and($item->fresh()->unit_price)->toBe(25_000);
});

it('decrements stock when the order is placed', function (): void {
    $vendor = vendorDeliveringHere(0);
    $product = publishedProduct($vendor, 10_000, stock: 5);

    resolve(AddToCart::class)->handle($this->cart, $product, 3);

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh(), $this->address);
    resolve(PlaceOrder::class)->handle($this->customer, $this->cart, $quote);

    expect($product->fresh()->stock_quantity)->toBe(2);
});

it('empties the cart once the order exists', function (): void {
    $vendor = vendorDeliveringHere(0);

    resolve(AddToCart::class)->handle($this->cart, publishedProduct($vendor, 10_000));

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh(), $this->address);
    resolve(PlaceOrder::class)->handle($this->customer, $this->cart, $quote);

    expect($this->cart->items()->count())->toBe(0);
});

it('refuses to place an order for a vendor that cannot sell', function (): void {
    $vendor = vendorDeliveringHere(1000);
    $product = publishedProduct($vendor, 10_000);

    resolve(AddToCart::class)->handle($this->cart, $product);

    // The subscription lapses after the item is already in the basket.
    $vendor->update(['subscription_status' => SubscriptionStatus::Expired]);

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh(), $this->address);

    expect($quote->isPlaceable())->toBeFalse()
        ->and($quote->blockingProblems())->toContain(CheckoutProblem::VendorCannotSell);

    resolve(PlaceOrder::class)->handle($this->customer, $this->cart, $quote);
})->throws(CheckoutException::class);

it('refuses to place an order when a vendor does not deliver to the address', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $product = publishedProduct($vendor, 10_000);

    resolve(AddToCart::class)->handle($this->cart, $product);

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh(), $this->address);

    expect($quote->isPlaceable())->toBeFalse()
        ->and($quote->blockingProblems())->toContain(CheckoutProblem::VendorDoesNotDeliver);
});

it('cannot be placed without a delivery address', function (): void {
    $vendor = vendorDeliveringHere(0);

    resolve(AddToCart::class)->handle($this->cart, publishedProduct($vendor, 10_000));

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh());

    expect($quote->isPlaceable())->toBeFalse();
});

it('blocks checkout when stock is insufficient', function (): void {
    $vendor = vendorDeliveringHere(0);
    $product = publishedProduct($vendor, 10_000, stock: 5);

    resolve(AddToCart::class)->handle($this->cart, $product, 5);

    $product->update(['stock_quantity' => 2]);

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh(), $this->address);

    expect($quote->isPlaceable())->toBeFalse()
        ->and($quote->blockingProblems())->toContain(CheckoutProblem::InsufficientStock);
});

it('reprices from the live product rather than the stale cart price', function (): void {
    $vendor = vendorDeliveringHere(0);
    $product = publishedProduct($vendor, 10_000);

    resolve(AddToCart::class)->handle($this->cart, $product);

    // The vendor raises the price while the cart sits open.
    $product->update(['price' => 12_000]);

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh(), $this->address);

    // A price change warns but does not block; the customer is charged the real price.
    expect($quote->subtotal)->toBe(12_000)
        ->and($quote->isPlaceable())->toBeTrue();
});

it('starts every order and vendor order as pending cash on delivery', function (): void {
    $vendor = vendorDeliveringHere(0);

    resolve(AddToCart::class)->handle($this->cart, publishedProduct($vendor, 10_000));

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh(), $this->address);
    $order = resolve(PlaceOrder::class)->handle($this->customer, $this->cart, $quote);

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->payment_method->value)->toBe('cash_on_delivery')
        ->and($order->currency)->toBe('RWF')
        ->and($order->placed_at)->not->toBeNull()
        ->and($order->vendorOrders()->sole()->status)->toBe(OrderStatus::Pending);
});

it('never records platform revenue against an order', function (): void {
    $vendor = vendorDeliveringHere(2000);

    resolve(AddToCart::class)->handle($this->cart, publishedProduct($vendor, 100_000));

    $quote = resolve(BuildCheckoutQuote::class)->handle($this->cart->fresh(), $this->address);
    $order = resolve(PlaceOrder::class)->handle($this->customer, $this->cart, $quote);

    $vendorOrder = $order->vendorOrders()->sole();

    // The vendor keeps 100% of the order value plus their own delivery fee. If a
    // commission were ever introduced, this assertion is what would catch it.
    expect($vendorOrder->total)->toBe($vendorOrder->subtotal + $vendorOrder->shipping_fee)
        ->and($order->total)->toBe(102_000);
});
