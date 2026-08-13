<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A vendor's own catalog.
 *
 * Creating and publishing carry `vendor.can-sell` because they put inventory in front
 * of customers. Editing, unpublishing and deleting deliberately do not: an expired
 * vendor keeps their catalog and can still tidy it.
 */
beforeEach(function (): void {
    Storage::fake('public');

    $this->vendor = Vendor::factory()->sellable()->create();
    $this->vendorUser = $this->vendor->user;
    $this->vendorUser->update(['role' => UserRole::Vendor]);

    $this->category = Category::factory()->create();
});

/**
 * A valid product payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productPayload(Category $category, array $overrides = []): array
{
    return [
        'name' => 'Ikawa Nziza Coffee',
        'category_id' => $category->uuid,
        'description' => 'Roasted in Huye.',
        'short_description' => 'Single origin.',
        'sku' => 'COF-001',
        'price' => 4500,
        'compare_at_price' => 6000,
        'stock_quantity' => 40,
        'low_stock_threshold' => 5,
        'weight' => 500,
        ...$overrides,
    ];
}

it('lists the vendor own products with their category and image gallery', function (): void {
    $product = Product::factory()->for($this->vendor)->for($this->category)->create();
    $product->addMedia(UploadedFile::fake()->image('bag.jpg'))->toMediaCollection('images');

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendor/products/index')
            ->has('products.data', 1)
            ->where('products.data.0.name', $product->name)
            ->where('products.data.0.category.name', $this->category->name)
            ->where('products.data.0.variants_count', 0)
            ->has('products.data.0.images', 1)
            ->missing('categories'),
        );
});

it('defers the category picker', function (): void {
    Category::factory()->inactive()->create();

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.products.index'), inertiaPartial('vendor/products/index', ['categories']))
        ->assertOk()
        ->assertJsonCount(1, 'props.categories')
        ->assertJsonPath('props.categories.0.name', $this->category->name);
});

it('filters the catalog by search, status and category', function (): void {
    $target = Product::factory()->for($this->vendor)->for($this->category)->published()->create([
        'name' => 'Findable Coffee',
        'sku' => 'FIND-1',
    ]);

    Product::factory()->for($this->vendor)->create(['name' => 'Something Else']);

    foreach (['Findable', 'FIND-1'] as $term) {
        $this->actingAs($this->vendorUser)
            ->get(route('vendor.products.index', ['search' => $term]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', $target->uuid),
            );
    }

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.products.index', ['status' => ProductStatus::Published->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $target->uuid)
            ->where('filters.status', ProductStatus::Published->value),
        );

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.products.index', ['category' => $this->category->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('filters.category', $this->category->uuid),
        );
});

it('ignores a status filter that is not a real status', function (): void {
    Product::factory()->count(2)->for($this->vendor)->create();

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.products.index', ['status' => 'imaginary']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 2));
});

it('creates a product as a draft with its images', function (): void {
    $this->actingAs($this->vendorUser)
        ->post(route('vendor.products.store'), productPayload($this->category, [
            'images' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')],
        ]))
        ->assertRedirect();

    $product = Product::query()->sole();

    expect($product->vendor_id)->toBe($this->vendor->id)
        ->and($product->category_id)->toBe($this->category->id)
        ->and($product->status)->toBe(ProductStatus::Draft)
        ->and($product->published_at)->toBeNull()
        ->and($product->currency)->toBe('RWF')
        ->and($product->slug)->toStartWith('ikawa-nziza-coffee-')
        ->and($product->getMedia('images'))->toHaveCount(2);
});

it('stores blank optional product fields as null', function (): void {
    $this->actingAs($this->vendorUser)
        ->post(route('vendor.products.store'), productPayload($this->category, [
            'description' => '',
            'short_description' => '',
            'sku' => '',
            'compare_at_price' => '',
            'weight' => '',
        ]))
        ->assertRedirect();

    $product = Product::query()->sole();

    expect($product->description)->toBeNull()
        ->and($product->short_description)->toBeNull()
        ->and($product->sku)->toBeNull()
        ->and($product->compare_at_price)->toBeNull()
        ->and($product->weight)->toBeNull();
});

it('refuses an original price that is not above the selling price', function (): void {
    $this->actingAs($this->vendorUser)
        ->post(route('vendor.products.store'), productPayload($this->category, [
            'price' => 5000,
            'compare_at_price' => 4000,
        ]))
        ->assertSessionHasErrors('compare_at_price');
});

it('refuses a duplicate sku within the same shop', function (): void {
    Product::factory()->for($this->vendor)->create(['sku' => 'DUPE-1']);

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.products.store'), productPayload($this->category, ['sku' => 'DUPE-1']))
        ->assertSessionHasErrors('sku');
});

it('allows the same sku in a different shop', function (): void {
    $rival = Vendor::factory()->sellable()->create();
    Product::factory()->for($rival)->create(['sku' => 'SHARED-1']);

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.products.store'), productPayload($this->category, ['sku' => 'SHARED-1']))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
});

it('refuses a category that is not active', function (): void {
    $inactive = Category::factory()->inactive()->create();

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.products.store'), productPayload($inactive))
        ->assertSessionHasErrors('category_id');
});

it('refuses a new product from a vendor who can no longer sell', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $expired->user->update(['role' => UserRole::Vendor]);

    $this->actingAs($expired->user)
        ->post(route('vendor.products.store'), productPayload($this->category))
        ->assertForbidden();

    expect(Product::query()->count())->toBe(0);
});

it('updates a product without rewriting its public slug', function (): void {
    $product = Product::factory()->for($this->vendor)->for($this->category)->create(['name' => 'Old Name']);
    $slug = $product->slug;

    $newCategory = Category::factory()->create();

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.products.update', $product), productPayload($newCategory, [
            'name' => 'New Name',
            'sku' => $product->sku,
            'images' => [UploadedFile::fake()->image('extra.jpg')],
        ]))
        ->assertRedirect();

    $product->refresh();

    expect($product->name)->toBe('New Name')
        ->and($product->slug)->toBe($slug)
        ->and($product->category_id)->toBe($newCategory->id)
        ->and($product->getMedia('images'))->toHaveCount(1);
});

it('keeps its own sku available when a product is edited', function (): void {
    $product = Product::factory()->for($this->vendor)->for($this->category)->create(['sku' => 'KEEP-1']);

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.products.update', $product), productPayload($this->category, ['sku' => 'KEEP-1']))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
});

it('lets a lapsed vendor keep tidying their own catalog', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $expired->user->update(['role' => UserRole::Vendor]);

    $product = Product::factory()->for($expired)->for($this->category)->create();

    $this->actingAs($expired->user)
        ->post(route('vendor.products.update', $product), productPayload($this->category, [
            'name' => 'Tidied Up',
            'sku' => $product->sku,
        ]))
        ->assertRedirect();

    expect($product->fresh()->name)->toBe('Tidied Up');
});

it('deletes a product nobody has bought', function (): void {
    $product = Product::factory()->for($this->vendor)->create();

    $this->actingAs($this->vendorUser)
        ->delete(route('vendor.products.destroy', $product))
        ->assertRedirect();

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse();
});

/**
 * Deleting would leave the customer's order without a link back to the product page,
 * and take its reviews with it. Archiving hides it just as well and keeps both.
 */
it('archives a product that has already been ordered', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create();

    $order = Order::factory()->create();
    $vendorOrder = VendorOrder::factory()->for($order)->for($this->vendor)->create();
    OrderItem::factory()->for($order)->for($vendorOrder)->for($product)->create();

    $this->actingAs($this->vendorUser)
        ->delete(route('vendor.products.destroy', $product))
        ->assertRedirect();

    $product->refresh();

    expect($product->status)->toBe(ProductStatus::Archived)
        ->and($product->published_at)->toBeNull();
});

it('publishes a draft product', function (): void {
    $product = Product::factory()->for($this->vendor)->create();

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.products.publish', $product))
        ->assertRedirect();

    $product->refresh();

    expect($product->status)->toBe(ProductStatus::Published)
        ->and($product->published_at)->not->toBeNull();
});

it('keeps the original publication date when a product is republished', function (): void {
    $publishedAt = now()->subMonth()->startOfDay();
    $product = Product::factory()->for($this->vendor)->create(['published_at' => $publishedAt]);

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.products.publish', $product))
        ->assertRedirect();

    expect($product->fresh()->published_at->toDateString())->toBe($publishedAt->toDateString());
});

it('unpublishes a product back to draft, whatever the subscription says', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $expired->user->update(['role' => UserRole::Vendor]);

    $product = Product::factory()->for($expired)->published()->create();

    $this->actingAs($expired->user)
        ->delete(route('vendor.products.unpublish', $product))
        ->assertRedirect();

    $product->refresh();

    expect($product->status)->toBe(ProductStatus::Draft)
        ->and($product->published_at)->toBeNull();
});

it('refuses to publish for a vendor who can no longer sell', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $expired->user->update(['role' => UserRole::Vendor]);

    $product = Product::factory()->for($expired)->create();

    $this->actingAs($expired->user)
        ->post(route('vendor.products.publish', $product))
        ->assertForbidden();

    expect($product->fresh()->status)->toBe(ProductStatus::Draft);
});

it('removes one image from a product gallery', function (): void {
    $product = Product::factory()->for($this->vendor)->create();

    $keep = $product->addMedia(UploadedFile::fake()->image('keep.jpg'))->toMediaCollection('images');
    $drop = $product->addMedia(UploadedFile::fake()->image('drop.jpg'))->toMediaCollection('images');

    $this->actingAs($this->vendorUser)
        ->delete(route('vendor.products.images.destroy', ['product' => $product, 'media' => $drop->uuid]))
        ->assertRedirect();

    expect($product->refresh()->getMedia('images')->pluck('uuid')->all())->toBe([$keep->uuid]);
});

it('hides an image identifier that is not on this product behind a 404', function (): void {
    $product = Product::factory()->for($this->vendor)->create();

    $this->actingAs($this->vendorUser)
        ->delete(route('vendor.products.images.destroy', ['product' => $product, 'media' => 'not-a-media-uuid']))
        ->assertNotFound();
});

it('forbids removing an image from another shop product', function (): void {
    $rival = Vendor::factory()->sellable()->create();
    $product = Product::factory()->for($rival)->create();
    $media = $product->addMedia(UploadedFile::fake()->image('theirs.jpg'))->toMediaCollection('images');

    $this->actingAs($this->vendorUser)
        ->delete(route('vendor.products.images.destroy', ['product' => $product, 'media' => $media->uuid]))
        ->assertForbidden();

    expect($product->refresh()->getMedia('images'))->toHaveCount(1);
});
