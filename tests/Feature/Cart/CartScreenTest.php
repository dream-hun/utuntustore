<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\Storage;

/**
 * The cart, grouped by vendor.
 *
 * Grouping is not cosmetic: a basket spanning three shops becomes three deliveries and
 * three cash payments at the door, and a customer who does not see that before checkout
 * is being misled.
 *
 * The cart itself is open to guests — nobody should have to register before they have
 * decided to buy.
 */
beforeEach(function (): void {
    Storage::fake('public');

    $this->customer = User::factory()->customer()->create();
    $this->vendor = Vendor::factory()->sellable()->create();
});

it('shows an empty cart as empty', function (): void {
    $this->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/cart')
            ->has('groups', 0)
            ->where('subtotal', 0)
            ->where('itemCount', 0)
            ->where('currency', 'RWF'),
        );
});

it('groups the basket by shop and totals each one separately', function (): void {
    $otherVendor = Vendor::factory()->sellable()->create();

    $cart = Cart::factory()->for($this->customer)->create();

    $mine = Product::factory()->for($this->vendor)->published()->create(['price' => 3000]);
    $mine->addMedia(UploadedFile::fake()->image('mine.jpg'))->toMediaCollection('images');

    $theirs = Product::factory()->for($otherVendor)->published()->create(['price' => 5000]);

    CartItem::factory()->for($cart)->for($mine)->create(['quantity' => 2, 'unit_price' => 3000]);
    CartItem::factory()->for($cart)->for($theirs)->create(['quantity' => 1, 'unit_price' => 5000]);

    $this->actingAs($this->customer)
        ->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('groups', 2)
            ->where('itemCount', 3)
            ->where('subtotal', 11000)
            ->where('groups.0.subtotal', 6000)
            ->where('groups.0.items.0.quantity', 2)
            ->where('groups.0.items.0.unit_price', 3000)
            ->where('groups.0.items.0.subtotal', 6000)
            ->where('groups.0.items.0.variant', null)
            ->where('groups.0.items.0.product.image_url', fn (?string $url): bool => $url !== null),
        );
});

it('prices a line from its variant when the line has one', function (): void {
    $cart = Cart::factory()->for($this->customer)->create();

    $product = Product::factory()->for($this->vendor)->published()->create([
        'price' => 3000,
        'stock_quantity' => 50,
    ]);

    $variant = ProductVariant::factory()->for($product)->create([
        'name' => 'Large',
        'price' => 4500,
        'stock_quantity' => 6,
    ]);

    CartItem::factory()->for($cart)->for($product)->create([
        'product_variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => 4500,
    ]);

    $this->actingAs($this->customer)
        ->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('groups.0.items.0.unit_price', 4500)
            ->where('groups.0.items.0.subtotal', 9000)
            ->where('groups.0.items.0.available_stock', 6)
            ->where('groups.0.items.0.max_quantity', 6)
            ->where('groups.0.items.0.variant.name', 'Large')
            ->where('subtotal', 9000),
        );
});

it('lets a guest fill a basket without registering', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create(['stock_quantity' => 5]);

    $this->from(route('products.show', $product))
        ->post(route('cart.store'), ['product' => $product->uuid, 'quantity' => 2])
        ->assertRedirect(route('products.show', $product));

    expect(CartItem::query()->sole()->quantity)->toBe(2);
});

it('adds a specific variant to the basket', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create(['stock_quantity' => 5]);
    $variant = ProductVariant::factory()->for($product)->create(['stock_quantity' => 5]);

    $this->post(route('cart.store'), [
        'product' => $product->uuid,
        'variant' => $variant->uuid,
        'quantity' => 1,
    ])->assertRedirect();

    expect(CartItem::query()->sole()->product_variant_id)->toBe($variant->id);
});

it('hides a variant that is no longer offered behind a 404', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create();
    $variant = ProductVariant::factory()->for($product)->inactive()->create();

    $this->post(route('cart.store'), [
        'product' => $product->uuid,
        'variant' => $variant->uuid,
        'quantity' => 1,
    ])->assertNotFound();
});

/**
 * Posting the id directly is the interesting case: the storefront already hides these
 * products, so this is the check that actually holds.
 */
it('refuses a product whose shop can no longer sell', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $product = Product::factory()->for($expired)->published()->create(['stock_quantity' => 10]);

    $this->post(route('cart.store'), ['product' => $product->uuid, 'quantity' => 1])
        ->assertNotFound();

    expect(CartItem::query()->count())->toBe(0);
});

it('refuses an unpublished product', function (): void {
    $product = Product::factory()->for($this->vendor)->create(['stock_quantity' => 10]);

    $this->post(route('cart.store'), ['product' => $product->uuid, 'quantity' => 1])
        ->assertNotFound();
});

it('reports a shortfall instead of overselling', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create(['stock_quantity' => 1]);

    $this->post(route('cart.store'), ['product' => $product->uuid, 'quantity' => 5])
        ->assertRedirect();

    expect(CartItem::query()->count())->toBe(0);
});

it('refuses a quantity below one', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create();

    $this->post(route('cart.store'), ['product' => $product->uuid, 'quantity' => 0])
        ->assertSessionHasErrors('quantity');
});

it('changes the quantity on a line', function (): void {
    $cart = Cart::factory()->for($this->customer)->create();
    $product = Product::factory()->for($this->vendor)->published()->create(['stock_quantity' => 9]);
    $item = CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);

    $this->actingAs($this->customer)
        ->patch(route('cart.update', $item), ['quantity' => 4])
        ->assertRedirect();

    expect($item->fresh()->quantity)->toBe(4);
});

/**
 * A stepper stepped down to nothing should remove the line, not error.
 */
it('treats a quantity of zero as a removal', function (): void {
    $cart = Cart::factory()->for($this->customer)->create();
    $item = CartItem::factory()->for($cart)->create(['quantity' => 2]);

    $this->actingAs($this->customer)
        ->patch(route('cart.update', $item), ['quantity' => 0])
        ->assertRedirect();

    expect(CartItem::query()->count())->toBe(0);
});

it('refuses a quantity the shop cannot supply', function (): void {
    $cart = Cart::factory()->for($this->customer)->create();
    $product = Product::factory()->for($this->vendor)->published()->create(['stock_quantity' => 2]);
    $item = CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);

    $this->actingAs($this->customer)
        ->patch(route('cart.update', $item), ['quantity' => 5])
        ->assertRedirect();

    expect($item->fresh()->quantity)->toBe(1);
});

it('removes a line from the basket', function (): void {
    $cart = Cart::factory()->for($this->customer)->create();
    $item = CartItem::factory()->for($cart)->create();

    $this->actingAs($this->customer)
        ->delete(route('cart.destroy', $item))
        ->assertRedirect();

    expect(CartItem::query()->count())->toBe(0);
});

/**
 * A cart line may only ever be touched through the cart it belongs to, which is what
 * stops one visitor editing another's basket by guessing a uuid.
 */
it('forbids touching a line in somebody else basket', function (): void {
    $stranger = User::factory()->customer()->create();
    $theirCart = Cart::factory()->for($stranger)->create();
    $theirItem = CartItem::factory()->for($theirCart)->create(['quantity' => 3]);

    $this->actingAs($this->customer)
        ->patch(route('cart.update', $theirItem), ['quantity' => 1])
        ->assertForbidden();

    $this->actingAs($this->customer)
        ->delete(route('cart.destroy', $theirItem))
        ->assertForbidden();

    expect($theirItem->fresh()->quantity)->toBe(3);
});

/**
 * The header badge and the cart drawer.
 *
 * Guests shop against a session-keyed cart, so a badge that only reads the user
 * relation sits on zero for everyone who has not signed in — which is most people
 * filling a basket, and makes the whole storefront feel broken.
 */
it('counts a guest basket in the header badge', function (): void {
    $product = Product::factory()->for($this->vendor)->published()->create(['price' => 3000]);

    $cart = Cart::factory()->guest()->create();
    CartItem::factory()->for($cart)->for($product)->create(['quantity' => 3, 'unit_price' => 3000]);

    // Driven against the middleware rather than over two HTTP calls: the suite runs on
    // the array session driver, which hands every request a brand new session id, so a
    // guest basket cannot survive from one test request to the next.
    $request = Request::create('/');
    $session = resolve(SessionManager::class)->driver();
    $session->setId($cart->session_id);

    $request->setLaravelSession($session);

    $shared = resolve(HandleInertiaRequests::class)->share($request);
    $cartCount = $shared['cartCount'];

    expect($cartCount)->toBeCallable()
        ->and($cartCount())->toBe(3);
});

it('counts a signed-in basket in the header badge', function (): void {
    $cart = Cart::factory()->for($this->customer)->create();
    $product = Product::factory()->for($this->vendor)->published()->create(['price' => 3000]);

    CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2, 'unit_price' => 3000]);

    $this->actingAs($this->customer)
        ->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('cartCount', 2));
});

it('shows nothing in the badge for a visitor who has never added anything', function (): void {
    $this->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('cartCount', 0));
});

/**
 * The basket is an optional prop so the drawer's query only runs when the drawer is
 * opened. It must therefore be absent until asked for, and complete once it is.
 */
it('keeps the drawer basket off the page until the drawer asks for it', function (): void {
    $this->get(route('cart.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->missing('cartPreview'));
});

it('builds the drawer basket with its shop, variant and image', function (): void {
    $cart = Cart::factory()->for($this->customer)->create();

    $withVariant = Product::factory()->for($this->vendor)->published()->create(['price' => 3000]);
    $withVariant->addMedia(UploadedFile::fake()->image('bag.jpg'))->toMediaCollection('images');

    $variant = ProductVariant::factory()->for($withVariant)->create([
        'name' => 'Large',
        'price' => 4000,
        'stock_quantity' => 9,
    ]);

    // No variant, no image: the other side of both branches the preview has to handle.
    $plain = Product::factory()->for($this->vendor)->published()->create(['price' => 1500]);

    CartItem::factory()->for($cart)->for($withVariant)->for($variant)->create([
        'quantity' => 2,
        'unit_price' => 4000,
    ]);
    CartItem::factory()->for($cart)->for($plain)->create(['quantity' => 1, 'unit_price' => 1500]);

    $this->actingAs($this->customer)
        ->get(route('cart.index'), inertiaPartial('storefront/cart', ['cartPreview']))
        ->assertOk()
        ->assertJsonCount(2, 'props.cartPreview.items')
        ->assertJsonPath('props.cartPreview.count', 3)
        ->assertJsonPath('props.cartPreview.subtotal', 9500)
        ->assertJsonPath('props.cartPreview.currency', 'RWF')
        ->assertJsonPath('props.cartPreview.items.0.vendor_name', $this->vendor->shop_name)
        ->assertJsonPath('props.cartPreview.items.0.variant_name', 'Large')
        ->assertJsonPath('props.cartPreview.items.0.unit_price', 4000)
        ->assertJsonPath('props.cartPreview.items.0.subtotal', 8000)
        ->assertJsonPath('props.cartPreview.items.1.variant_name', null)
        ->assertJsonPath('props.cartPreview.items.1.image_url', null);
});

it('gives the drawer an empty basket when the visitor has no cart', function (): void {
    // Asked for from the catalog rather than the cart page: visiting the cart page
    // resolves a cart into existence, so it can never exercise the no-cart path the
    // drawer hits on every other screen.
    $this->get(route('shop'), inertiaPartial('storefront/catalog', ['cartPreview']))
        ->assertOk()
        ->assertJsonCount(0, 'props.cartPreview.items')
        ->assertJsonPath('props.cartPreview.count', 0)
        ->assertJsonPath('props.cartPreview.subtotal', 0);
});
