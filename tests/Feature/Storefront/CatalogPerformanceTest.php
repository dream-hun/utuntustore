<?php

declare(strict_types=1);

use App\Actions\Storefront\PickFeaturedVendors;
use App\Actions\Storefront\ResolveCategoryBranch;
use App\Actions\Vendor\BuildVendorDashboard;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\District;
use App\Models\Product;
use App\Models\Province;
use App\Models\Sector;
use App\Models\User;
use App\Models\Vendor;
use App\Support\LocationDirectory;
use Illuminate\Support\Facades\DB;

/**
 * Guards the query cost of the pages customers actually hit, and the consistency of
 * the category rules the catalog and the category page both depend on.
 *
 * These are cheap to keep honest and expensive to discover in production, where the
 * symptom is a slow home page on a phone rather than a failing assertion.
 */

/**
 * Count the queries a callback runs.
 */
function queriesDuring(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    $count = count(DB::getQueryLog());

    DB::disableQueryLog();

    return $count;
}

it('serves the district list from cache after the first read', function (): void {
    $province = Province::factory()->create();
    District::factory()->count(3)->for($province)->create();

    $locations = resolve(LocationDirectory::class);

    expect(queriesDuring(fn () => $locations->districts()))->toBeGreaterThan(0);

    // Rwanda's districts change when a boundary is redrawn, which is to say never.
    // Every subsequent read on any request must cost nothing.
    expect(queriesDuring(fn () => resolve(LocationDirectory::class)->districts()))->toBe(0)
        ->and(queriesDuring(fn () => resolve(LocationDirectory::class)->districtOptions()))->toBe(0);
});

it('caches sectors per district rather than all at once', function (): void {
    $province = Province::factory()->create();
    $gasabo = District::factory()->for($province)->create();
    $kicukiro = District::factory()->for($province)->create();

    Sector::factory()->count(2)->for($gasabo)->create();
    Sector::factory()->count(2)->for($kicukiro)->create();

    $locations = resolve(LocationDirectory::class);

    $locations->sectors($gasabo->uuid);

    expect(queriesDuring(fn () => $locations->sectors($gasabo->uuid)))->toBe(0)
        // A second district is a separate entry, not a cache miss on the whole set.
        ->and(queriesDuring(fn () => $locations->sectors($kicukiro->uuid)))->toBeGreaterThan(0);

    expect($locations->sectors($gasabo->uuid))->toHaveCount(2);
});

it('yields no sectors for a district that does not exist', function (): void {
    expect(resolve(LocationDirectory::class)->sectors(''))->toBe([])
        ->and(resolve(LocationDirectory::class)->sectors('not-a-uuid'))->toBe([]);
});

it('excludes inactive subcategories from a category branch', function (): void {
    $parent = Category::factory()->create(['is_active' => true]);
    $live = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);
    $retired = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => false]);

    $branch = resolve(ResolveCategoryBranch::class);

    expect($branch->handle($parent)->pluck('id')->all())->toBe([$live->id])
        ->and($branch->ids($parent))->toBe([$parent->id, $live->id])
        ->and($branch->ids($parent))->not->toContain($retired->id);
});

it('hides products filed under a deactivated subcategory from the catalog filter', function (): void {
    $parent = Category::factory()->create(['is_active' => true]);
    $retired = Category::factory()->create(['parent_id' => $parent->id, 'is_active' => false]);

    $vendor = Vendor::factory()->sellable()->create();

    $visible = Product::factory()->for($vendor)->published()->create(['category_id' => $parent->id]);
    $hidden = Product::factory()->for($vendor)->published()->create(['category_id' => $retired->id]);

    $this->get(route('shop', ['category' => $parent->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', $visible->name));

    expect($hidden->category_id)->toBe($retired->id);
});

it('features only vendors that are eligible to sell', function (): void {
    Vendor::factory()->count(3)->sellable()->create();
    $expired = Vendor::factory()->expired()->create();

    $featured = resolve(PickFeaturedVendors::class)->handle();

    expect($featured)->toHaveCount(3)
        ->and($featured->pluck('id')->all())->not->toContain($expired->id);
});

it('picks featured vendors without sorting the whole vendor table', function (): void {
    Vendor::factory()->count(12)->sellable()->create();

    $featured = resolve(PickFeaturedVendors::class);

    $featured->handle(4);

    DB::flushQueryLog();
    DB::enableQueryLog();

    expect($featured->handle(4))->toHaveCount(4);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // A warm request is the keyed lookup for the four rows shown plus their media —
    // a fixed cost, whether the platform has twelve shops or twelve thousand.
    expect($queries)->toHaveCount(2);

    // Never ORDER BY RAND(), which makes MySQL number and sort every eligible row
    // to pick four.
    foreach ($queries as $query) {
        expect(mb_strtolower((string) $query['query']))->not->toContain('rand(');
    }
});

it('shares no vendor context for a customer', function (): void {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.vendor', null));
});

/**
 * Capture the SQL a callback runs.
 *
 * @return array<int, string>
 */
function sqlDuring(Closure $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    $log = DB::getQueryLog();

    DB::disableQueryLog();

    return array_map(static fn (array $entry): string => (string) $entry['query'], $log);
}

it('shares the cart badge for one query, on every page in the application', function (): void {
    $customer = User::factory()->customer()->create();
    $vendor = Vendor::factory()->sellable()->create();
    $product = Product::factory()->for($vendor)->published()->create();

    $cart = Cart::factory()->create(['user_id' => $customer->id]);
    CartItem::factory()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'product_variant_id' => null,
        'quantity' => 2,
        'unit_price' => $product->price,
    ]);

    // The account overview defers every one of its own props, so a plain visit to it
    // is a clean measurement of what the shared props alone cost. `cartCount` is a
    // closure, and a closure prop is NOT lazy — Inertia resolves it on every request,
    // including admin and vendor screens that never render a basket. Finding the cart
    // row and then summing it was two round trips on all of them; it is one aggregate.
    $queries = sqlDuring(fn () => $this->actingAs($customer)
        ->get(route('account.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('cartCount', 2)));

    expect($queries)->toHaveCount(1);
});

it('does not rebuild the catalog for a partial reload that never asked for it', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    Product::factory()->count(3)->for($vendor)->published()->create();

    // What the cart drawer sends when it opens. The action still runs in full, so a
    // prop computed eagerly into a local would be paid for and then dropped on the
    // floor — every open, and twice per quantity tap. Behind a closure it never runs.
    $queries = sqlDuring(fn () => $this->get(route('shop'), inertiaPartial('storefront/catalog', ['cartPreview']))
        ->assertOk());

    foreach ($queries as $query) {
        expect($query)->not->toContain('from "products"');
    }
});

it('builds the vendor dashboard in one pass per table, not one per number', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    Product::factory()->count(3)->for($vendor)->published()->create();

    // Nine counts over three tables, each re-scanning the same vendor's rows to answer
    // a different question about them, and all nine serial.
    expect(queriesDuring(fn () => resolve(BuildVendorDashboard::class)->handle($vendor)))
        ->toBeLessThanOrEqual(3);
});
