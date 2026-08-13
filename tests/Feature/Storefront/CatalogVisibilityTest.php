<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;

/**
 * The storefront's single most important rule: what a customer can see is decided
 * by Product::sellable(), which is publication AND the owning vendor's eligibility
 * to sell. A lapsed vendor must vanish from the shop while every row they own stays
 * exactly where it is.
 */
it('lists a sellable vendor product in the catalog', function (): void {
    $product = Product::factory()
        ->for(Vendor::factory()->sellable())
        ->published()
        ->create();

    $this->get(route('shop'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/catalog')
            ->where('products.data.0.name', $product->name),
        );
});

it('hides an expired vendor from the catalog, their products and their shop page', function (): void {
    $vendor = Vendor::factory()->expired()->create();
    $product = Product::factory()->for($vendor)->published()->create();

    // The data is untouched — it is only hidden.
    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();

    $this->get(route('shop'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('products.total', 0));

    $this->get(route('products.show', $product->slug))->assertNotFound();
    $this->get(route('vendors.show', $vendor->slug))->assertNotFound();
});

it('still shows a vendor inside their subscription grace period', function (): void {
    $vendor = Vendor::factory()->grace()->create();
    $product = Product::factory()->for($vendor)->published()->create();

    $this->get(route('shop'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('products.total', 1));

    $this->get(route('products.show', $product->slug))->assertOk();
    $this->get(route('vendors.show', $vendor->slug))->assertOk();
});

it('does not show a draft product', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $draft = Product::factory()->for($vendor)->create();

    $this->get(route('shop'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('products.total', 0));

    $this->get(route('products.show', $draft->slug))->assertNotFound();
});

it('does not show a product scheduled to publish in the future', function (): void {
    $vendor = Vendor::factory()->sellable()->create();

    Product::factory()->for($vendor)->published()->create([
        'published_at' => now()->addWeek(),
    ]);

    $this->get(route('shop'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('products.total', 0));
});

it('searches the catalog by name', function (): void {
    $vendor = Vendor::factory()->sellable()->create();

    Product::factory()->for($vendor)->published()->create(['name' => 'Ikirezi Coffee Beans']);
    Product::factory()->for($vendor)->published()->create(['name' => 'Cotton Bedsheet']);

    $this->get(route('shop', ['search' => 'Ikirezi']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('products.total', 1)
            ->where('products.data.0.name', 'Ikirezi Coffee Beans'),
        );
});

it('filters the catalog by price range and sorts by price', function (): void {
    $vendor = Vendor::factory()->sellable()->create();

    Product::factory()->for($vendor)->published()->create(['name' => 'Cheap', 'price' => 5_000]);
    Product::factory()->for($vendor)->published()->create(['name' => 'Mid', 'price' => 50_000]);
    Product::factory()->for($vendor)->published()->create(['name' => 'Dear', 'price' => 500_000]);

    $this->get(route('shop', ['min_price' => 10_000, 'max_price' => 600_000, 'sort' => 'price_asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('products.total', 2)
            ->where('products.data.0.name', 'Mid')
            ->where('products.data.1.name', 'Dear'),
        );
});

it('lists a category with the products of its child categories', function (): void {
    $vendor = Vendor::factory()->sellable()->create();

    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    Product::factory()->for($vendor)->for($parent, 'category')->published()->create();
    Product::factory()->for($vendor)->for($child, 'category')->published()->create();
    Product::factory()->for($vendor)->published()->create();

    $this->get(route('categories.show', $parent->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/category')
            ->where('products.total', 2)
            ->has('children', 1),
        );
});

it('404s an inactive category', function (): void {
    $category = Category::factory()->inactive()->create();

    $this->get(route('categories.show', $category->slug))->assertNotFound();
});

it('shows a vendor shop page with only that vendor sellable products', function (): void {
    $vendor = Vendor::factory()->sellable()->create();
    $other = Vendor::factory()->sellable()->create();

    Product::factory()->for($vendor)->published()->count(2)->create();
    Product::factory()->for($other)->published()->create();
    Product::factory()->for($vendor)->create();

    $this->get(route('vendors.show', $vendor->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/vendor')
            ->where('vendor.shop_name', $vendor->shop_name)
            ->where('products.total', 2),
        );
});

/**
 * "You may also like" on a product page.
 *
 * Recommendations reach across shops on purpose: on a marketplace the useful
 * comparison is what else the customer could buy instead, which usually means
 * another vendor. The sellable rule still applies, so a lapsed vendor cannot get
 * back in front of customers through someone else's product page.
 */
it('recommends other sellable products from the same category', function (): void {
    $category = Category::factory()->create();
    $otherCategory = Category::factory()->create();

    $vendor = Vendor::factory()->sellable()->create();
    $otherVendor = Vendor::factory()->sellable()->create();
    $expired = Vendor::factory()->expired()->create();

    $product = Product::factory()->for($vendor)->for($category)->published()->create();

    $sameCategory = Product::factory()->for($otherVendor)->for($category)->published()->create();

    Product::factory()->for($vendor)->for($otherCategory)->published()->create();
    Product::factory()->for($expired)->for($category)->published()->create();
    Product::factory()->for($vendor)->for($category)->create();

    $this->get(route('products.show', $product), inertiaPartial('storefront/product', ['related']))
        ->assertOk()
        ->assertJsonCount(1, 'props.related')
        ->assertJsonPath('props.related.0.id', $sameCategory->uuid);
});

it('recommends nothing when a product is alone in its category', function (): void {
    $product = Product::factory()
        ->for(Vendor::factory()->sellable())
        ->for(Category::factory())
        ->published()
        ->create();

    $this->get(route('products.show', $product), inertiaPartial('storefront/product', ['related']))
        ->assertOk()
        ->assertJsonCount(0, 'props.related');
});
