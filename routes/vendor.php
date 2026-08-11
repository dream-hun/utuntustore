<?php

declare(strict_types=1);

use App\Http\Controllers\Vendor\DashboardController;
use App\Http\Controllers\Vendor\DeliveryAreaController;
use App\Http\Controllers\Vendor\InventoryController;
use App\Http\Controllers\Vendor\OrderController;
use App\Http\Controllers\Vendor\ProductController;
use App\Http\Controllers\Vendor\ProductImageController;
use App\Http\Controllers\Vendor\ProductPublicationController;
use App\Http\Controllers\Vendor\SalesController;
use App\Http\Controllers\Vendor\ShopController;
use App\Http\Controllers\Vendor\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vendor Area
|--------------------------------------------------------------------------
|
| The whole area requires the vendor role and resolves the current vendor, but
| it must stay reachable when a subscription has expired: an expired vendor
| loses selling rights, not access to their own dashboard, orders and history.
|
| The 'vendor.can-sell' middleware therefore goes on individual routes that
| create or publish inventory, never on the group.
|
*/

Route::middleware(['auth', 'verified', 'role:vendor', 'vendor'])
    ->prefix('vendor')
    ->name('vendor.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        /*
         | Shop profile. Editing stays open to an expired vendor — the shop is already
         | hidden from the storefront, and keeping it accurate is part of coming back.
         */
        Route::get('shop', [ShopController::class, 'edit'])->name('shop.edit');
        Route::post('shop', [ShopController::class, 'update'])->name('shop.update');

        /*
         | Subscription is read-only for a vendor: payment happens off-platform and only
         | an admin who confirmed receiving the money can record it. This route must stay
         | reachable when expired; it is the screen an expired vendor needs most.
         */
        Route::get('subscription', SubscriptionController::class)->name('subscription.index');

        /*
         | Catalog. Creating and publishing put inventory in front of customers and are
         | gated on a live subscription; editing, unpublishing and deleting are not.
         */
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])
            ->middleware('vendor.can-sell')
            ->name('products.store');
        Route::post('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
        Route::post('products/{product}/publish', [ProductPublicationController::class, 'store'])
            ->middleware('vendor.can-sell')
            ->name('products.publish');
        Route::delete('products/{product}/publish', [ProductPublicationController::class, 'destroy'])->name('products.unpublish');
        Route::delete('products/{product}/images/{media}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');

        /*
         | Inventory. A vendor delivers out of their own stock room, so keeping the count
         | honest stays possible whatever their subscription says.
         */
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::put('inventory/products/{product}', [InventoryController::class, 'updateProduct'])->name('inventory.products.update');
        Route::put('inventory/variants/{variant}', [InventoryController::class, 'updateVariant'])->name('inventory.variants.update');

        /*
         | Delivery coverage is shop configuration rather than an act of selling, so it is
         | never gated — an expired vendor can have their coverage ready for the day they
         | renew.
         */
        Route::get('delivery', [DeliveryAreaController::class, 'index'])->name('delivery.index');
        Route::post('delivery', [DeliveryAreaController::class, 'store'])->name('delivery.store');
        Route::put('delivery/{deliveryArea}', [DeliveryAreaController::class, 'update'])->name('delivery.update');
        Route::delete('delivery/{deliveryArea}', [DeliveryAreaController::class, 'destroy'])->name('delivery.destroy');

        /*
         | Orders. The route key is the VendorOrder, never the parent Order: a vendor
         | reaches customer data only through their own slice of a basket.
         */
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{vendorOrder}', [OrderController::class, 'show'])->name('orders.show');
        Route::put('orders/{vendorOrder}/status', [OrderController::class, 'update'])->name('orders.status.update');

        /*
         | Cash the vendor already collected. Never a balance, never an amount owed.
         */
        Route::get('sales', SalesController::class)->name('sales.index');
    });
