<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The platform-wide catalog, and the admin's ability to add to it.
 *
 * A product always belongs to a shop, so the one field this form has that a vendor's
 * own form does not is the shop it is for. Everything else is validated identically,
 * so a product an admin writes is one its owner can still save.
 */
beforeEach(function (): void {
    Storage::fake('public');

    $this->admin = User::factory()->admin()->create();
    $this->vendor = Vendor::factory()->sellable()->create();
    $this->category = Category::factory()->create();
});

/**
 * A valid admin product payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function adminProductPayload(Vendor $vendor, Category $category, array $overrides = []): array
{
    return [
        'vendor' => $vendor->uuid,
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

it('lists every shop products with the shop that owns them', function (): void {
    $product = Product::factory()->for($this->vendor)->for($this->category)->create();
    $product->addMedia(UploadedFile::fake()->image('bag.jpg'))->toMediaCollection('images');

    Product::factory()->for(Vendor::factory()->sellable())->for($this->category)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/products/index')
            ->has('products.data', 2)
            ->where('products.data.1.name', $product->name)
            ->where('products.data.1.vendor.shop_name', $this->vendor->shop_name)
            ->where('products.data.1.category.name', $this->category->name)
            ->where('products.data.1.variants_count', 0)
            ->has('products.data.1.images', 1)
            ->missing('categories')
            ->missing('vendors'),
        );
});

it('defers both pickers and only offers approved shops', function (): void {
    Category::factory()->inactive()->create();
    Vendor::factory()->create(); // Pending: never offered.
    $expired = Vendor::factory()->expired()->create();

    $response = $this->actingAs($this->admin)
        ->get(route('admin.products.index'), inertiaPartial('admin/products/index', ['categories', 'vendors']))
        ->assertOk()
        ->assertJsonCount(1, 'props.categories')
        ->assertJsonPath('props.categories.0.name', $this->category->name)
        ->assertJsonCount(2, 'props.vendors');

    $shops = collect($response->json('props.vendors'))->keyBy('shop_name');

    expect($shops[$this->vendor->shop_name]['can_sell'])->toBeTrue()
        ->and($shops[$expired->shop_name]['can_sell'])->toBeFalse();
});

it('filters the catalog by search, shop, category and status', function (): void {
    $target = Product::factory()->for($this->vendor)->for($this->category)->published()->create([
        'name' => 'Findable Coffee',
        'sku' => 'FIND-1',
    ]);

    Product::factory()->for(Vendor::factory()->sellable())->create(['name' => 'Something Else']);

    foreach (['Findable', 'FIND-1'] as $term) {
        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['search' => $term]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', $target->uuid),
            );
    }

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['vendor' => $this->vendor->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $target->uuid)
            ->where('filters.vendor', $this->vendor->uuid),
        );

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['category' => $this->category->uuid]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('filters.category', $this->category->uuid),
        );

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['status' => ProductStatus::Published->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.id', $target->uuid)
            ->where('filters.status', ProductStatus::Published->value),
        );
});

it('ignores a status filter that is not a real status', function (): void {
    Product::factory()->count(2)->for($this->vendor)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.products.index', ['status' => 'imaginary']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products.data', 2));
});

it('adds a product to the chosen shop as a draft, with its images', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductPayload($this->vendor, $this->category, [
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

it('publishes the new product straight away when asked', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductPayload($this->vendor, $this->category, [
            'publish' => true,
        ]))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $product = Product::query()->sole();

    expect($product->status)->toBe(ProductStatus::Published)
        ->and($product->published_at)->not->toBeNull();
});

/**
 * Publishing answers to the shop's selling eligibility, never to the admin's role:
 * putting stock in front of buyers for a shop that has lapsed is exactly what the
 * subscription is supposed to stop.
 */
it('refuses to publish into a shop that cannot sell', function (): void {
    $expired = Vendor::factory()->expired()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductPayload($expired, $this->category, [
            'publish' => true,
        ]))
        ->assertSessionHasErrors('publish');

    expect(Product::query()->count())->toBe(0);
});

it('still adds a draft to a shop that cannot sell', function (): void {
    $expired = Vendor::factory()->expired()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductPayload($expired, $this->category))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect(Product::query()->sole()->status)->toBe(ProductStatus::Draft);
});

it('refuses a shop that is not approved', function (): void {
    $pending = Vendor::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductPayload($pending, $this->category))
        ->assertSessionHasErrors('vendor');

    expect(Product::query()->count())->toBe(0);
});

it('refuses a product with no shop', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductPayload($this->vendor, $this->category, [
            'vendor' => null,
        ]))
        ->assertSessionHasErrors('vendor');

    expect(Product::query()->count())->toBe(0);
});

it('stores blank optional product fields as null', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductPayload($this->vendor, $this->category, [
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
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductPayload($this->vendor, $this->category, [
            'price' => 5000,
            'compare_at_price' => 4000,
        ]))
        ->assertSessionHasErrors('compare_at_price');
});

it('refuses a category that is not active', function (): void {
    $inactive = Category::factory()->inactive()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductPayload($this->vendor, $inactive))
        ->assertSessionHasErrors('category_id');
});

/**
 * The SKU is scoped to the chosen shop, not to the admin doing the typing, which is
 * what the products table's composite unique index enforces.
 */
it('refuses a duplicate sku within the chosen shop', function (): void {
    Product::factory()->for($this->vendor)->create(['sku' => 'DUPE-1']);

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductPayload($this->vendor, $this->category, ['sku' => 'DUPE-1']))
        ->assertSessionHasErrors('sku');
});

it('allows the same sku in a different shop', function (): void {
    $rival = Vendor::factory()->sellable()->create();
    Product::factory()->for($rival)->create(['sku' => 'SHARED-1']);

    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), adminProductPayload($this->vendor, $this->category, ['sku' => 'SHARED-1']))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
});

/**
 * Editing is the mirror of creating, minus the shop: a product cannot change catalogs,
 * so the shop comes from the routed product rather than from the payload.
 */
it('edits any shop product without rewriting its public slug', function (): void {
    $product = Product::factory()->for($this->vendor)->for($this->category)->create(['name' => 'Old Name']);
    $slug = $product->slug;

    $newCategory = Category::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), adminProductPayload($this->vendor, $newCategory, [
            'name' => 'New Name',
            'sku' => $product->sku,
            'price' => 7000,
            'compare_at_price' => null,
            'stock_quantity' => 12,
            'images' => [UploadedFile::fake()->image('extra.jpg')],
        ]))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $product->refresh();

    expect($product->name)->toBe('New Name')
        ->and($product->slug)->toBe($slug)
        ->and($product->category_id)->toBe($newCategory->id)
        ->and($product->price)->toBe(7000)
        ->and($product->stock_quantity)->toBe(12)
        ->and($product->getMedia('images'))->toHaveCount(1);
});

it('keeps a product in its own shop whatever the payload asks for', function (): void {
    $rival = Vendor::factory()->sellable()->create();
    $product = Product::factory()->for($this->vendor)->for($this->category)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), adminProductPayload($rival, $this->category, [
            'sku' => $product->sku,
        ]))
        ->assertRedirect();

    expect($product->fresh()->vendor_id)->toBe($this->vendor->id);
});

it('keeps a product own sku available when it is edited', function (): void {
    $product = Product::factory()->for($this->vendor)->for($this->category)->create(['sku' => 'KEEP-1']);

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), adminProductPayload($this->vendor, $this->category, ['sku' => 'KEEP-1']))
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();
});

it('refuses an edit that would duplicate another sku in the same shop', function (): void {
    Product::factory()->for($this->vendor)->create(['sku' => 'TAKEN-1']);
    $product = Product::factory()->for($this->vendor)->for($this->category)->create(['sku' => 'MINE-1']);

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), adminProductPayload($this->vendor, $this->category, ['sku' => 'TAKEN-1']))
        ->assertSessionHasErrors('sku');

    expect($product->fresh()->sku)->toBe('MINE-1');
});

it('edits a product belonging to a shop that can no longer sell', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $product = Product::factory()->for($expired)->for($this->category)->create();

    $this->actingAs($this->admin)
        ->post(route('admin.products.update', $product), adminProductPayload($expired, $this->category, [
            'name' => 'Tidied Up',
            'sku' => $product->sku,
        ]))
        ->assertRedirect();

    expect($product->fresh()->name)->toBe('Tidied Up');
});

it('deletes a product nobody has bought', function (): void {
    $product = Product::factory()->for($this->vendor)->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.products.destroy', $product))
        ->assertRedirect();

    expect(Product::query()->whereKey($product->id)->exists())->toBeFalse();
});

/**
 * Deleting would leave the customer order without a link back to the product page, and
 * take its reviews with it. Archiving hides it just as well and keeps both.
 */
it('archives a product that has already been ordered', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create();

    $order = Order::factory()->create();
    $vendorOrder = VendorOrder::factory()->for($order)->for($this->vendor)->create();
    OrderItem::factory()->for($order)->for($vendorOrder)->for($product)->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.products.destroy', $product))
        ->assertRedirect();

    $product->refresh();

    expect($product->status)->toBe(ProductStatus::Archived)
        ->and($product->published_at)->toBeNull();
});
