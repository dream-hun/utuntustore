<?php

declare(strict_types=1);

use App\Actions\Checkout\ResolveDeliveryArea;
use App\Actions\Orders\SyncOrderStatus;
use App\Actions\Storefront\PickFeaturedVendors;
use App\Actions\Storefront\SearchSellableProducts;
use App\Enums\OrderStatus;
use App\Exceptions\CheckoutException;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vendor;
use App\Support\Checkout\CheckoutProblem;
use App\Support\Checkout\CheckoutQuote;
use App\Support\LocationDirectory;
use App\Support\Settings;

/**
 * The quote object, the exception it raises, and the small pieces of infrastructure
 * around checkout that no HTTP route reaches on its own.
 */
it('refuses to place a quote carrying an order-level blocking problem', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $address = Address::factory()->create();

    $quote = new CheckoutQuote(
        vendorQuotes: [new App\Support\Checkout\VendorQuote(
            vendor: $vendor,
            lines: [],
            subtotal: 0,
            shippingFee: 0,
            discount: 0,
            total: 0,
            deliveryArea: null,
        )],
        subtotal: 0,
        discount: 0,
        shippingFee: 0,
        tax: 0,
        total: 0,
        shippingAddress: $address,
        coupon: null,
        problems: [CheckoutProblem::CouponUnavailable],
    );

    expect($quote->isPlaceable())->toBeFalse()
        ->and($quote->blockingProblems())->toBe([CheckoutProblem::CouponUnavailable]);
});

/**
 * A price change is a warning, not a refusal: the quote re-prices from the product
 * and the customer is told what changed before they confirm.
 */
it('lets a quote through when its only problem is a price change', function (): void {
    $vendor = Vendor::factory()->sellable()->create();

    $quote = new CheckoutQuote(
        vendorQuotes: [new App\Support\Checkout\VendorQuote(
            vendor: $vendor,
            lines: [],
            subtotal: 1000,
            shippingFee: 0,
            discount: 0,
            total: 1000,
            deliveryArea: null,
        )],
        subtotal: 1000,
        discount: 0,
        shippingFee: 0,
        tax: 0,
        total: 1000,
        shippingAddress: Address::factory()->create(),
        coupon: null,
        problems: [CheckoutProblem::PriceChanged],
    );

    expect($quote->isPlaceable())->toBeTrue()
        ->and($quote->blockingProblems())->toBe([]);
});

it('falls back to a generic message when a quote fails for no stated reason', function (): void {
    $quote = new CheckoutQuote(
        vendorQuotes: [],
        subtotal: 0,
        discount: 0,
        shippingFee: 0,
        tax: 0,
        total: 0,
        shippingAddress: null,
        coupon: null,
    );

    $exception = CheckoutException::notPlaceable($quote);

    expect($exception->getMessage())->toBe('This order cannot be placed.')
        ->and($exception->problems)->toBe([]);
});

it('names the product that sold out mid-checkout', function (): void {
    $exception = CheckoutException::stockChanged('Ikawa Coffee');

    expect($exception->getMessage())->toBe('Ikawa Coffee sold out while you were checking out.')
        ->and($exception->problems)->toBe([CheckoutProblem::InsufficientStock]);
});

it('resolves no delivery areas when asked about no vendors', function (): void {
    $address = Address::factory()->create();

    expect(resolve(ResolveDeliveryArea::class)->forVendors([], $address))->toBe([]);
});

it('leaves an order with no vendor orders exactly as it was', function (): void {
    $order = Order::factory()->create(['status' => OrderStatus::Pending]);

    $synced = resolve(SyncOrderStatus::class)->handle($order);

    expect($synced->status)->toBe(OrderStatus::Pending)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
});

it('features nothing when no shop is eligible', function (): void {
    Vendor::factory()->expired()->create();

    expect(resolve(PickFeaturedVendors::class)->handle())->toHaveCount(0);
});

it('features a handful of eligible shops', function (): void {
    Vendor::factory()->count(3)->sellable()->create();

    expect(resolve(PickFeaturedVendors::class)->handle(2))->toHaveCount(2);
});

/**
 * Production carries a FULLTEXT index over the searchable product columns; SQLite,
 * which the suite runs on, has none. Both branches are real, so the MySQL one is
 * driven through a MySQL-grammar builder — compiling the SQL needs no server.
 */
it('searches through the fulltext index on MySQL', function (): void {
    $query = Product::on('mysql');

    resolve(SearchSellableProducts::class)->handle($query, 'ikawa');

    expect(mb_strtolower($query->toSql()))->toContain('match')
        ->and(mb_strtolower($query->toSql()))->toContain('against');
});

it('falls back to a like search elsewhere, escaping the wildcards', function (): void {
    $query = Product::query();

    resolve(SearchSellableProducts::class)->handle($query, '50%');

    expect($query->getBindings())->toContain('%50\\%%');
});

it('does not touch the query when the search term is blank', function (): void {
    $query = Product::query();
    $before = $query->toSql();

    resolve(SearchSellableProducts::class)->handle($query, '   ');

    expect($query->toSql())->toBe($before);
});

it('reads a raw setting and falls back when it is unset', function (): void {
    $settings = resolve(Settings::class);

    $settings->set('vendor_subscription_fee', 33000);

    expect($settings->get('vendor_subscription_fee'))->toBe('33000')
        ->and($settings->get('never_set_at_all', 'fallback'))->toBe('fallback');
});

it('stores platform settings as rows', function (): void {
    Setting::factory()->subscriptionFee()->create();

    expect(Setting::query()->where('key', 'vendor_subscription_fee')->sole()->value)->toBe('20000');
});

/**
 * The geography is cached whole. Flushing has to drop the per-district sector entries
 * too, and their only handle is the district uuids inside the cached district list.
 */
it('drops the cached geography including every district sector list', function (): void {
    $sector = App\Models\Sector::factory()->create();
    $district = $sector->district;

    $locations = resolve(LocationDirectory::class);

    expect($locations->districts())->toHaveCount(1)
        ->and($locations->sectors($district->uuid))->toHaveCount(1)
        ->and($locations->sectorOptions($district->uuid))->toHaveCount(1);

    App\Models\Sector::factory()->create(['district_id' => $district->id]);

    // Still the cached answer.
    expect($locations->sectors($district->uuid))->toHaveCount(1);

    $locations->flush();

    expect($locations->sectors($district->uuid))->toHaveCount(2)
        ->and($locations->districtOptions())->toHaveCount(1);
});

it('serves no sectors for a district that was never asked about', function (): void {
    expect(resolve(LocationDirectory::class)->sectors(''))->toBe([]);
});

it('applies the strongest password rules only in production', function (): void {
    expect(Illuminate\Validation\Rules\Password::default()->toPasswordRulesString())
        ->not->toContain('minlength: 12');

    $this->app->detectEnvironment(fn (): string => 'production');

    $rules = Illuminate\Validation\Rules\Password::default()->toPasswordRulesString();

    expect($rules)->toContain('minlength: 12')
        ->and($rules)->toContain('required: special');
});

it('signs a customer in with no cart as carrying nothing', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cartCount', 0)
            ->where('auth.vendor', null),
        );
});
