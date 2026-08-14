<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\District;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sector;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use App\Support\Checkout\CheckoutProblem;

/**
 * The checkout screen renders exactly the quote that will be persisted, so a customer
 * can never confirm one set of numbers and be held to another. Nothing here prices or
 * validates anything itself — BuildCheckoutQuote is the single source of truth and
 * PlaceOrder is the only thing that writes an order.
 */
beforeEach(function (): void {
    $this->customer = User::factory()->customer()->create();

    $this->sector = Sector::factory()->create();
    $this->district = $this->sector->district;

    $this->address = Address::factory()->for($this->customer)->isDefault()->create([
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
    ]);

    $this->cart = Cart::factory()->for($this->customer)->create();
});

/**
 * A sellable shop that delivers to the test address for a known fee.
 */
function shopDeliveringToTestAddress(int $deliveryFee = 1000): Vendor
{
    $vendor = Vendor::factory()->sellable()->create();

    VendorDeliveryArea::factory()->for($vendor)->create([
        'district_id' => test()->district->id,
        'sector_id' => null,
        'delivery_fee' => $deliveryFee,
        'estimated_days_min' => 1,
        'estimated_days_max' => 3,
        'is_active' => true,
    ]);

    return $vendor;
}

/**
 * Put a published product from the given shop into the test cart.
 */
function basketLine(Vendor $vendor, int $price, int $quantity = 1, int $stock = 10): Product
{
    $product = Product::factory()->for($vendor)->published()->create([
        'price' => $price,
        'stock_quantity' => $stock,
    ]);

    CartItem::factory()->for(test()->cart)->for($product)->create([
        'quantity' => $quantity,
        'unit_price' => $price,
    ]);

    return $product;
}

it('renders the quote the customer will be held to', function (): void {
    $vendor = shopDeliveringToTestAddress(1500);
    basketLine($vendor, 4000, 2);

    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/checkout')
            ->where('quote.subtotal', 8000)
            ->where('quote.shipping_fee', 1500)
            ->where('quote.discount', 0)
            ->where('quote.total', 9500)
            ->where('quote.item_count', 2)
            ->where('quote.vendor_count', 1)
            ->where('quote.is_placeable', true)
            ->has('quote.problems', 0)
            ->where('quote.vendor_quotes.0.delivers', true)
            ->where('quote.vendor_quotes.0.estimated_days_min', 1)
            ->where('quote.vendor_quotes.0.estimated_days_max', 3)
            ->where('quote.vendor_quotes.0.lines.0.quantity', 2)
            ->where('quote.vendor_quotes.0.lines.0.available_stock', 10)
            ->has('addresses', 1)
            ->where('selectedAddressId', $this->address->uuid)
            ->where('coupon', null)
            ->has('districts', 1)
            ->has('sectors', 0),
        );
});

it('reports an empty cart as the reason it cannot be ordered', function (): void {
    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('quote.is_placeable', false)
            ->has('quote.problems', 1)
            ->where('quote.problems.0.code', CheckoutProblem::EmptyCart->value)
            ->where('quote.problems.0.blocking', true),
        );
});

it('tells the customer which shop does not deliver to them', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    basketLine($vendor, 5000);

    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('quote.is_placeable', false)
            ->where('quote.vendor_quotes.0.delivers', false)
            ->where('quote.vendor_quotes.0.estimated_days_min', null)
            ->where('quote.vendor_quotes.0.problems.0.code', CheckoutProblem::VendorDoesNotDeliver->value),
        );
});

it('tells the customer when a shop stopped accepting orders mid-basket', function (): void {
    $vendor = shopDeliveringToTestAddress();
    basketLine($vendor, 5000);

    $vendor->update(['subscription_status' => SubscriptionStatus::Expired]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('quote.is_placeable', false)
            ->where('quote.vendor_quotes.0.problems.0.code', CheckoutProblem::VendorCannotSell->value),
        );
});

it('flags a line whose product was unpublished after it went in the basket', function (): void {
    $vendor = shopDeliveringToTestAddress();
    $product = basketLine($vendor, 5000);

    $product->update(['published_at' => null]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('quote.is_placeable', false)
            ->where('quote.vendor_quotes.0.lines.0.problems.0.code', CheckoutProblem::ProductUnavailable->value),
        );
});

it('flags a variant that was withdrawn after it went in the basket', function (): void {
    $vendor = shopDeliveringToTestAddress();

    $product = Product::factory()->for($vendor)->published()->create(['price' => 5000, 'stock_quantity' => 10]);
    $variant = ProductVariant::factory()->for($product)->inactive()->create([
        'name' => 'Large',
        'price' => 6000,
        'stock_quantity' => 5,
    ]);

    CartItem::factory()->for($this->cart)->for($product)->create([
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => 6000,
    ]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('quote.vendor_quotes.0.lines.0.variant_name', 'Large')
            // Priced from the variant, and drawing on the variant's own stock pool.
            ->where('quote.vendor_quotes.0.lines.0.unit_price', 6000)
            ->where('quote.vendor_quotes.0.lines.0.available_stock', 5)
            ->where('quote.vendor_quotes.0.lines.0.problems.0.code', CheckoutProblem::ProductUnavailable->value)
            ->where('quote.is_placeable', false),
        );
});

it('re-prices from the live product and says so without blocking', function (): void {
    $vendor = shopDeliveringToTestAddress();
    $product = basketLine($vendor, 5000);

    $product->update(['price' => 7000]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('quote.vendor_quotes.0.lines.0.unit_price', 7000)
            ->where('quote.subtotal', 7000)
            ->where('quote.vendor_quotes.0.lines.0.problems.0.code', CheckoutProblem::PriceChanged->value)
            ->where('quote.vendor_quotes.0.lines.0.problems.0.blocking', false)
            // A price change is a warning, not a refusal: the customer is told before
            // they confirm and the quote simply carries the new price.
            ->where('quote.is_placeable', true),
        );
});

it('flags a line that outgrew the shop stock', function (): void {
    $vendor = shopDeliveringToTestAddress();
    $product = basketLine($vendor, 5000, 4);

    $product->update(['stock_quantity' => 1]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('quote.is_placeable', false)
            ->where('quote.vendor_quotes.0.lines.0.problems.0.code', CheckoutProblem::InsufficientStock->value),
        );
});

it('applies a coupon and reports it as applied', function (): void {
    $vendor = shopDeliveringToTestAddress(1000);
    basketLine($vendor, 10000);

    $coupon = Coupon::factory()->create(['code' => 'SAVE10', 'value' => 10]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index', ['coupon' => $coupon->code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('coupon.code', 'SAVE10')
            ->where('coupon.applied', true)
            ->where('coupon.discount', 1000)
            ->where('quote.discount', 1000)
            ->where('quote.total', 10000)
            ->where('quote.vendor_quotes.0.discount', 1000),
        );
});

it('reports a coupon that buys nothing on this cart as not applicable', function (): void {
    $vendor = shopDeliveringToTestAddress();
    basketLine($vendor, 5000);

    $coupon = Coupon::factory()->expired()->create(['code' => 'TOOLATE']);

    $this->actingAs($this->customer)
        ->get(route('checkout.index', ['coupon' => $coupon->code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('coupon.applied', false)
            ->where('coupon.discount', 0),
        );
});

it('reports a code that is not a coupon at all as not applicable', function (): void {
    $vendor = shopDeliveringToTestAddress();
    basketLine($vendor, 5000);

    $this->actingAs($this->customer)
        ->get(route('checkout.index', ['coupon' => 'MADE-UP']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('coupon.applied', false));
});

it('honours an explicitly chosen address over the default', function (): void {
    $vendor = shopDeliveringToTestAddress();
    basketLine($vendor, 5000);

    $other = Address::factory()->for($this->customer)->create([
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
    ]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index', ['address' => $other->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('selectedAddressId', $other->uuid));
});

it('falls back to the default when the chosen address is not the customer own', function (): void {
    $vendor = shopDeliveringToTestAddress();
    basketLine($vendor, 5000);

    $stranger = Address::factory()->create();

    $this->actingAs($this->customer)
        ->get(route('checkout.index', ['address' => $stranger->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('selectedAddressId', $this->address->uuid));
});

it('falls back to the newest address when the customer has no default', function (): void {
    $this->address->update(['is_default' => false]);

    $newest = Address::factory()->for($this->customer)->create([
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
    ]);

    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('selectedAddressId', $newest->uuid));
});

it('quotes with no address at all when the customer has none', function (): void {
    $stranger = User::factory()->customer()->create();

    $this->actingAs($stranger)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedAddressId', null)
            ->where('quote.is_placeable', false),
        );
});

it('serves the sectors of the district being filled in, never all of them', function (): void {
    Sector::factory()->count(2)->create(['district_id' => $this->district->id]);
    Sector::factory()->create();

    $this->actingAs($this->customer)
        ->get(route('checkout.index', ['district' => $this->district->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('sectors', 3));
});

it('adds an address inline and re-quotes against it, keeping the coupon', function (): void {
    $vendor = shopDeliveringToTestAddress();
    basketLine($vendor, 5000);

    $coupon = Coupon::factory()->create(['code' => 'KEEPME']);

    $this->actingAs($this->customer)
        ->post(route('checkout.addresses.store'), [
            'first_name' => 'Aline',
            'last_name' => 'Uwase',
            'phone' => '+250788000111',
            'district_id' => $this->district->uuid,
            'sector_id' => $this->sector->uuid,
            'cell' => 'Kacyiru',
            'village' => 'Kamatamu',
            'address_line' => 'KG 7 Ave',
            'landmark' => 'Near the church',
            'coupon' => $coupon->code,
        ])
        ->assertRedirect();

    $created = Address::query()->where('first_name', 'Aline')->sole();

    expect($created->district_id)->toBe($this->district->id)
        ->and($created->sector_id)->toBe($this->sector->id)
        ->and($created->landmark)->toBe('Near the church')
        // It is not the first address, so it does not silently take over as default.
        ->and($created->is_default)->toBeFalse();
});

it('makes an inline address the default when it is the customer first', function (): void {
    $stranger = User::factory()->customer()->create();

    $this->actingAs($stranger)
        ->post(route('checkout.addresses.store'), [
            'first_name' => 'First',
            'last_name' => 'Address',
            'phone' => '+250788000111',
            'district_id' => $this->district->uuid,
            'sector_id' => $this->sector->uuid,
        ])
        ->assertRedirect();

    $created = Address::query()->where('user_id', $stranger->id)->sole();

    expect($created->is_default)->toBeTrue()
        ->and($created->cell)->toBeNull()
        ->and($created->village)->toBeNull()
        ->and($created->address_line)->toBeNull()
        ->and($created->landmark)->toBeNull();
});

it('demotes the previous default when an inline address asks to take over', function (): void {
    $this->actingAs($this->customer)
        ->post(route('checkout.addresses.store'), [
            'first_name' => 'Takeover',
            'last_name' => 'Address',
            'phone' => '+250788000111',
            'district_id' => $this->district->uuid,
            'sector_id' => $this->sector->uuid,
            'is_default' => true,
        ])
        ->assertRedirect();

    expect($this->address->fresh()->is_default)->toBeFalse()
        ->and($this->customer->addresses()->where('is_default', true)->count())->toBe(1);
});

it('refuses an inline address whose sector is in another district', function (): void {
    $foreignSector = Sector::factory()->create();

    $this->actingAs($this->customer)
        ->post(route('checkout.addresses.store'), [
            'first_name' => 'Wrong',
            'last_name' => 'Pair',
            'phone' => '+250788000111',
            'district_id' => $this->district->uuid,
            'sector_id' => $foreignSector->uuid,
        ])
        ->assertSessionHasErrors('sector_id');
});

it('places the order and hands the customer their confirmation', function (): void {
    $vendor = shopDeliveringToTestAddress(1000);
    basketLine($vendor, 4000, 2);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), ['address_id' => $this->address->uuid, 'expected_total' => 9000])
        ->assertRedirect();

    $order = Order::query()->sole();

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and($order->total)->toBe(9000)
        ->and(CartItem::query()->count())->toBe(0);

    $this->actingAs($this->customer)
        ->get(route('checkout.confirmation', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/confirmation')
            ->where('order.order_number', $order->order_number)
            ->where('order.total', 9000)
            ->where('order.shipping_fee', 1000)
            ->where('order.shipping_address.first_name', $this->address->first_name)
            ->has('order.vendor_orders', 1)
            ->where('order.vendor_orders.0.total', 9000)
            ->where('order.vendor_orders.0.vendor.phone', $vendor->phone)
            ->has('order.vendor_orders.0.items', 1),
        );
});

it('places an order with a coupon on it', function (): void {
    $vendor = shopDeliveringToTestAddress(0);
    basketLine($vendor, 10000);

    $coupon = Coupon::factory()->create(['code' => 'TENOFF', 'value' => 10]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'address_id' => $this->address->uuid,
            'coupon_code' => $coupon->code,
            'expected_total' => 9000,
        ])
        ->assertRedirect();

    expect(Order::query()->sole()->discount)->toBe(1000)
        ->and($coupon->fresh()->used_count)->toBe(1);
});

it('refuses to place an order that is not placeable', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    basketLine($vendor, 5000);

    $this->actingAs($this->customer)
        ->from(route('checkout.index'))
        ->post(route('checkout.store'), ['address_id' => $this->address->uuid, 'expected_total' => 5000])
        ->assertRedirect(route('checkout.index'));

    expect(Order::query()->count())->toBe(0)
        ->and(CartItem::query()->count())->toBe(1);
});

/**
 * A price that moved between rendering the screen and submitting it stops the order.
 *
 * PriceChanged is deliberately not a blocking problem: cart_items.unit_price is only a
 * display snapshot and nothing ever refreshes it, so blocking on one would wedge the
 * customer with no way through but emptying their basket. The screen posts the total it
 * showed instead, and a re-quote that disagrees sends them back to the new figure.
 */
it('refuses to place an order at a total the customer was never shown', function (): void {
    $vendor = shopDeliveringToTestAddress(0);
    $product = basketLine($vendor, 10000);

    $product->update(['price' => 12000]);

    $this->actingAs($this->customer)
        ->from(route('checkout.index'))
        ->post(route('checkout.store'), [
            'address_id' => $this->address->uuid,
            'expected_total' => 10000,
        ])
        ->assertRedirect(route('checkout.index'));

    expect(Order::query()->count())->toBe(0)
        ->and(CartItem::query()->count())->toBe(1);
});

it('places the order once the customer confirms the new total', function (): void {
    $vendor = shopDeliveringToTestAddress(0);
    $product = basketLine($vendor, 10000);

    $product->update(['price' => 12000]);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [
            'address_id' => $this->address->uuid,
            'expected_total' => 12000,
        ])
        ->assertRedirect();

    expect(Order::query()->sole()->total)->toBe(12000);
});

/**
 * A coupon that lapsed mid-checkout is the same problem wearing a different hat: the
 * discount silently drops to zero and no problem is recorded at all, so the total is
 * the only thing that gives it away.
 */
it('refuses to place an order whose coupon lapsed while the customer was deciding', function (): void {
    $vendor = shopDeliveringToTestAddress(0);
    basketLine($vendor, 10000);

    $coupon = Coupon::factory()->create([
        'code' => 'LAPSING',
        'value' => 10,
        'usage_limit' => 1,
        'used_count' => 0,
    ]);

    $coupon->update(['used_count' => 1]);

    $this->actingAs($this->customer)
        ->from(route('checkout.index'))
        ->post(route('checkout.store'), [
            'address_id' => $this->address->uuid,
            'coupon_code' => $coupon->code,
            'expected_total' => 9000,
        ])
        ->assertRedirect(route('checkout.index'));

    expect(Order::query()->count())->toBe(0);
});

it('refuses to place an order that agrees to no total at all', function (): void {
    $vendor = shopDeliveringToTestAddress(0);
    basketLine($vendor, 5000);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), ['address_id' => $this->address->uuid])
        ->assertSessionHasErrors('expected_total');

    expect(Order::query()->count())->toBe(0);
});

it('refuses to place an order against somebody else address', function (): void {
    $vendor = shopDeliveringToTestAddress();
    basketLine($vendor, 5000);

    $stranger = Address::factory()->create();

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), ['address_id' => $stranger->uuid, 'expected_total' => 5000])
        ->assertSessionHasErrors('address_id');
});

it('refuses to place an order with no address chosen', function (): void {
    $vendor = shopDeliveringToTestAddress();
    basketLine($vendor, 5000);

    $this->actingAs($this->customer)
        ->post(route('checkout.store'), [])
        ->assertSessionHasErrors('address_id');
});

it('keeps a confirmation private to the customer who placed it', function (): void {
    $order = Order::factory()->for($this->customer)->create([
        'shipping_address_id' => $this->address->id,
    ]);

    $stranger = User::factory()->customer()->create();

    $this->actingAs($stranger)
        ->get(route('checkout.confirmation', $order))
        ->assertForbidden();
});

it('refuses the whole checkout to a guest', function (): void {
    foreach ([route('checkout.index')] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }

    $this->post(route('checkout.store'), [])->assertRedirect(route('login'));
    $this->post(route('checkout.addresses.store'), [])->assertRedirect(route('login'));
});

it('quotes a district picker even for a customer with no districts filled in', function (): void {
    District::factory()->count(2)->create();

    $this->actingAs($this->customer)
        ->get(route('checkout.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('districts', 3));
});
