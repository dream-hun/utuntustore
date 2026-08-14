<?php

declare(strict_types=1);

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\ResolveCart;
use App\Actions\Checkout\BuildCheckoutQuote;
use App\Actions\Checkout\PlaceOrder;
use App\Enums\CouponType;
use App\Exceptions\CheckoutException;
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
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Str;

/**
 * How a discount is spread across a multi-vendor basket.
 *
 * This matters because the platform takes no commission: every discount is money out
 * of the granting vendor's pocket, so one shop's promotion must never reduce another
 * shop's takings, and the arithmetic must add up to the discount exactly.
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
 * A sellable shop that delivers to the test address for free.
 */
function freeDeliveryShop(): Vendor
{
    $vendor = Vendor::factory()->sellable()->create();

    VendorDeliveryArea::factory()->for($vendor)->create([
        'district_id' => test()->district->id,
        'sector_id' => null,
        'delivery_fee' => 0,
        'is_active' => true,
    ]);

    return $vendor;
}

function cartLineFor(Vendor $vendor, int $price, int $quantity = 1): Product
{
    $product = Product::factory()->for($vendor)->published()->create([
        'price' => $price,
        'stock_quantity' => 50,
    ]);

    CartItem::factory()->for(test()->cart)->for($product)->create([
        'quantity' => $quantity,
        'unit_price' => $price,
    ]);

    return $product;
}

function quoteTheCart(?Coupon $coupon = null): App\Support\Checkout\CheckoutQuote
{
    return resolve(BuildCheckoutQuote::class)->handle(test()->cart->fresh(), test()->address, $coupon);
}

it('spreads a platform-wide discount proportionally and to the last franc', function (): void {
    $shopA = freeDeliveryShop();
    $shopB = freeDeliveryShop();

    cartLineFor($shopA, 7000);
    cartLineFor($shopB, 3000);

    $coupon = Coupon::factory()->create(['type' => CouponType::Percentage, 'value' => 10]);

    $quote = quoteTheCart($coupon);

    $shares = array_map(
        static fn (App\Support\Checkout\VendorQuote $vendorQuote): int => $vendorQuote->discount,
        $quote->vendorQuotes,
    );

    expect($quote->discount)->toBe(1000)
        ->and(array_sum($shares))->toBe(1000)
        ->and($shares)->toBe([700, 300]);
});

/**
 * The francs lost to rounding go to the shops rounded down hardest rather than being
 * lost, so the vendor shares always add up to the discount the customer was shown.
 */
it('hands out the rounding remainder rather than losing it', function (): void {
    $shopA = freeDeliveryShop();
    $shopB = freeDeliveryShop();
    $shopC = freeDeliveryShop();

    cartLineFor($shopA, 3333);
    cartLineFor($shopB, 3333);
    cartLineFor($shopC, 3334);

    $coupon = Coupon::factory()->fixed()->create(['value' => 1000]);

    $quote = quoteTheCart($coupon);

    $shares = array_map(
        static fn (App\Support\Checkout\VendorQuote $vendorQuote): int => $vendorQuote->discount,
        $quote->vendorQuotes,
    );

    expect($quote->discount)->toBe(1000)
        ->and(array_sum($shares))->toBe(1000);
});

/**
 * The remainder goes out one franc per shop, not all of it to whoever sorts first.
 *
 * Three equal shops sharing a 101-franc coupon are each owed 33 and a third. Handing
 * the two leftover francs to the hardest-rounded shops in turn gives 34/34/33. Handing
 * one shop as many as it had room for gave it both — 35/33/33, the right total but the
 * wrong shop absorbing the rounding, and a split that moved with iteration order.
 *
 * Asserting only that the shares sum to the discount, as the test above does, passes
 * either way; the per-shop figures are what pins the rule down.
 */
it('hands each shop at most one of the leftover francs', function (): void {
    $shopA = freeDeliveryShop();
    $shopB = freeDeliveryShop();
    $shopC = freeDeliveryShop();

    cartLineFor($shopA, 1000);
    cartLineFor($shopB, 1000);
    cartLineFor($shopC, 1000);

    $coupon = Coupon::factory()->fixed()->create(['value' => 101]);

    $quote = quoteTheCart($coupon);

    $shares = array_map(
        static fn (App\Support\Checkout\VendorQuote $vendorQuote): int => $vendorQuote->discount,
        $quote->vendorQuotes,
    );

    expect($quote->discount)->toBe(101)
        ->and(array_sum($shares))->toBe(101)
        ->and($shares)->toBe([34, 34, 33]);
});

/**
 * The remainder is not guaranteed to fit in the last shop.
 *
 * Every other shop's share is rounded down, so the leftover handed to the last one is
 * its proportional share plus everyone else's rounding. When that shop is the cheapest
 * in the basket and the coupon covers nearly all of it, the leftover exceeds what that
 * shop is owed and the clamp silently drops the excess — leaving the customer's order
 * discounted by more than the shops actually give up.
 */
it('spends the whole discount even when the last shop is too small to absorb the remainder', function (): void {
    $shopA = freeDeliveryShop();
    $shopB = freeDeliveryShop();
    $shopC = freeDeliveryShop();

    cartLineFor($shopA, 12000);
    cartLineFor($shopB, 25000);
    cartLineFor($shopC, 500);

    $coupon = Coupon::factory()->fixed()->create(['value' => 37499]);

    $quote = quoteTheCart($coupon);

    $shares = array_map(
        static fn (App\Support\Checkout\VendorQuote $vendorQuote): int => $vendorQuote->discount,
        $quote->vendorQuotes,
    );

    $vendorTotals = array_sum(array_map(
        static fn (App\Support\Checkout\VendorQuote $vendorQuote): int => $vendorQuote->total,
        $quote->vendorQuotes,
    ));

    expect($quote->discount)->toBe(37499)
        ->and(array_sum($shares))->toBe(37499)
        ->and($vendorTotals)->toBe($quote->total);
});

it('leaves other shops untouched when a coupon belongs to one of them', function (): void {
    $granting = freeDeliveryShop();
    $other = freeDeliveryShop();

    cartLineFor($granting, 10000);
    cartLineFor($other, 10000);

    $coupon = Coupon::factory()->create([
        'vendor_id' => $granting->id,
        'type' => CouponType::Percentage,
        'value' => 50,
    ]);

    $quote = quoteTheCart($coupon);

    $byVendor = [];

    foreach ($quote->vendorQuotes as $vendorQuote) {
        $byVendor[$vendorQuote->vendor->id] = $vendorQuote;
    }

    expect($quote->discount)->toBe(5000)
        ->and($byVendor[$granting->id]->discount)->toBe(5000)
        ->and($byVendor[$granting->id]->total)->toBe(5000)
        ->and($byVendor[$other->id]->discount)->toBe(0)
        ->and($byVendor[$other->id]->total)->toBe(10000);
});

it('skips a shop whose basket costs nothing when allocating', function (): void {
    $paying = freeDeliveryShop();
    $freebies = freeDeliveryShop();

    cartLineFor($paying, 10000);
    cartLineFor($freebies, 0);

    $coupon = Coupon::factory()->create(['type' => CouponType::Percentage, 'value' => 10]);

    $quote = quoteTheCart($coupon);

    $byVendor = [];

    foreach ($quote->vendorQuotes as $vendorQuote) {
        $byVendor[$vendorQuote->vendor->id] = $vendorQuote;
    }

    expect($quote->discount)->toBe(1000)
        ->and($byVendor[$paying->id]->discount)->toBe(1000)
        ->and($byVendor[$freebies->id]->discount)->toBe(0);
});

it('is worth nothing below the minimum order it asks for', function (): void {
    $shop = freeDeliveryShop();
    cartLineFor($shop, 5000);

    $coupon = Coupon::factory()->create([
        'type' => CouponType::Percentage,
        'value' => 20,
        'minimum_order_amount' => 20000,
    ]);

    $quote = quoteTheCart($coupon);

    expect($quote->discount)->toBe(0)
        ->and($quote->coupon)->toBeNull();
});

it('is worth nothing when it belongs to a shop that is not in the basket', function (): void {
    $shop = freeDeliveryShop();
    cartLineFor($shop, 5000);

    $coupon = Coupon::factory()->create([
        'vendor_id' => Vendor::factory()->sellable()->create()->id,
        'type' => CouponType::Percentage,
        'value' => 20,
    ]);

    expect(quoteTheCart($coupon)->discount)->toBe(0);
});

it('never discounts more than its own ceiling', function (): void {
    $shop = freeDeliveryShop();
    cartLineFor($shop, 100000);

    $coupon = Coupon::factory()->create([
        'type' => CouponType::Percentage,
        'value' => 50,
        'maximum_discount' => 5000,
    ]);

    expect(quoteTheCart($coupon)->discount)->toBe(5000);
});

/**
 * A discount can never exceed what it is discounting, and never leaves a vendor owing
 * the customer money.
 */
it('never discounts more than the basket is worth', function (): void {
    $shop = freeDeliveryShop();
    cartLineFor($shop, 2000);

    $coupon = Coupon::factory()->fixed()->create(['value' => 999000]);

    $quote = quoteTheCart($coupon);

    expect($quote->discount)->toBe(2000)
        ->and($quote->total)->toBe(0);
});

it('adds to an existing basket line rather than duplicating it', function (): void {
    $shop = freeDeliveryShop();
    $product = Product::factory()->for($shop)->published()->create(['price' => 1500, 'stock_quantity' => 20]);

    $addToCart = resolve(AddToCart::class);

    $addToCart->handle($this->cart, $product, 2);
    $item = $addToCart->handle($this->cart->fresh(), $product, 3);

    expect(CartItem::query()->count())->toBe(1)
        ->and($item->quantity)->toBe(5)
        ->and($item->unit_price)->toBe(1500);
});

/**
 * Losing a basket at the sign-in step is one of the most expensive things a shop can
 * do, so a guest cart is folded into the account cart rather than discarded.
 */
it('sums quantities when the same product is in both the guest and account basket', function (): void {
    $shop = freeDeliveryShop();
    $product = Product::factory()->for($shop)->published()->create(['stock_quantity' => 20]);
    $other = Product::factory()->for($shop)->published()->create(['stock_quantity' => 20]);

    $sessionId = mb_substr(Str::padRight('merge', 40, 'abcdefghijklmnopqrstuvwxyz0123456789'), 0, 40);

    $guestCart = Cart::factory()->create(['user_id' => null, 'session_id' => $sessionId]);
    CartItem::factory()->for($guestCart)->for($product)->create(['quantity' => 2]);
    CartItem::factory()->for($guestCart)->for($other)->create(['quantity' => 1]);

    CartItem::factory()->for($this->cart)->for($product)->create(['quantity' => 3]);

    $session = new Store('testing', new ArraySessionHandler(120), $sessionId);
    $session->start();

    $request = Request::create('/cart');
    $request->setLaravelSession($session);
    $request->setUserResolver(fn (): User => $this->customer);

    $merged = resolve(ResolveCart::class)->handle($request);

    expect($merged->id)->toBe($this->cart->id)
        ->and(Cart::query()->whereKey($guestCart->id)->exists())->toBeFalse()
        ->and($merged->items()->where('product_id', $product->id)->sum('quantity'))->toBe(5)
        ->and($merged->items()->count())->toBe(2);
});

it('writes a variant line onto the order and takes stock from the variant', function (): void {
    $shop = freeDeliveryShop();

    $product = Product::factory()->for($shop)->published()->create([
        'price' => 4000,
        'stock_quantity' => 10,
        'sku' => 'BASE-1',
    ]);

    $variant = ProductVariant::factory()->for($product)->create([
        'name' => 'Large',
        'sku' => 'VAR-1',
        'price' => 5000,
        'stock_quantity' => 6,
    ]);

    CartItem::factory()->for($this->cart)->for($product)->create([
        'product_variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => 5000,
    ]);

    $order = resolve(PlaceOrder::class)->handle($this->customer, $this->cart, quoteTheCart());

    $item = $order->items()->sole();

    expect($item->product_variant_id)->toBe($variant->id)
        ->and($item->variant_name)->toBe('Large')
        ->and($item->sku)->toBe('VAR-1')
        ->and($item->unit_price)->toBe(5000)
        ->and($variant->fresh()->stock_quantity)->toBe(4)
        // The base product's own stock is a separate pool and stays untouched.
        ->and($product->fresh()->stock_quantity)->toBe(10);
});

/**
 * Stock is re-read under a row lock inside the transaction. Without it, two customers
 * checking out the last item would both pass validation and drive stock negative.
 */
it('refuses the order when stock ran out between quoting and writing', function (): void {
    $shop = freeDeliveryShop();
    $product = cartLineFor($shop, 5000, 3);

    $quote = quoteTheCart();

    $product->update(['stock_quantity' => 1]);

    expect(fn (): mixed => resolve(PlaceOrder::class)->handle($this->customer, $this->cart, $quote))
        ->toThrow(CheckoutException::class, $product->name.' sold out while you were checking out.');
});

it('refuses the order when a product vanished between quoting and writing', function (): void {
    $shop = freeDeliveryShop();
    $product = cartLineFor($shop, 5000);

    $quote = quoteTheCart();

    $product->orderItems()->delete();
    $product->delete();

    expect(fn (): mixed => resolve(PlaceOrder::class)->handle($this->customer, $this->cart, $quote))
        ->toThrow(CheckoutException::class, 'An item sold out while you were checking out.');
});

it('refuses the order when the last redemption of a coupon was taken meanwhile', function (): void {
    $shop = freeDeliveryShop();
    cartLineFor($shop, 10000);

    $coupon = Coupon::factory()->create([
        'type' => CouponType::Percentage,
        'value' => 10,
        'usage_limit' => 1,
        'used_count' => 0,
    ]);

    $quote = quoteTheCart($coupon);

    $coupon->update(['used_count' => 1]);

    expect(fn (): mixed => resolve(PlaceOrder::class)->handle($this->customer, $this->cart, $quote))
        ->toThrow(CheckoutException::class, 'That coupon was fully redeemed while you were checking out.');

    expect(Order::query()->count())->toBe(0);
});

it('sorts the catalog by price in both directions', function (): void {
    $shop = freeDeliveryShop();

    Product::factory()->for($shop)->published()->create(['name' => 'Cheap', 'price' => 1000]);
    Product::factory()->for($shop)->published()->create(['name' => 'Dear', 'price' => 9000]);

    $this->get(route('shop', ['sort' => 'price_desc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('products.data.0.name', 'Dear'));

    $this->get(route('shop', ['sort' => 'price_asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('products.data.0.name', 'Cheap'));
});

it('filters the catalog to one shop, and only a shop that may sell', function (): void {
    $shop = freeDeliveryShop();
    Product::factory()->for($shop)->published()->create(['name' => 'On Sale Here']);

    $lapsed = Vendor::factory()->expired()->create();
    Product::factory()->for($lapsed)->published()->create(['name' => 'From A Lapsed Shop']);

    $this->get(route('shop', ['vendor' => $shop->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'On Sale Here')
            ->where('filters.vendor', $shop->slug),
        );

    // A lapsed shop's slug resolves to nothing, so it can never be browsed by hand
    // -editing the query string either.
    $this->get(route('shop', ['vendor' => $lapsed->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'On Sale Here')
            ->where('filters.vendor', null),
        );
});

it('shows a shop page with where it delivers and what it charges', function (): void {
    $shop = freeDeliveryShop();

    VendorDeliveryArea::factory()->for($shop)->create([
        'district_id' => $this->district->id,
        'sector_id' => $this->sector->id,
        'delivery_fee' => 1500,
        'estimated_days_min' => 1,
        'estimated_days_max' => 2,
    ]);

    VendorDeliveryArea::factory()->for($shop)->inactive()->create([
        'district_id' => District::factory()->create()->id,
        'sector_id' => null,
    ]);

    $this->get(route('vendors.show', $shop))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->missing('deliveryAreas'));

    $this->get(route('vendors.show', $shop), inertiaPartial('storefront/vendor', ['deliveryAreas']))
        ->assertOk()
        // The inactive area is not coverage the customer can rely on.
        ->assertJsonCount(2, 'props.deliveryAreas')
        ->assertJsonPath('props.deliveryAreas.0.district', $this->district->name);
});
