<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\VendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Area
|--------------------------------------------------------------------------
|
| Platform operations: vendor approval, the subscription revenue ledger,
| categories, orders oversight and platform settings.
|
| The whole area is admin-only. `role:admin` also refuses suspended accounts,
| so no controller here has to check that itself.
|
*/

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
        Route::get('vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
        Route::patch('vendors/{vendor}/moderate', [VendorController::class, 'moderate'])->name('vendors.moderate');

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');

        // Cancel, never delete: subscription rows are the platform's revenue ledger.
        Route::patch('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])
            ->name('subscriptions.cancel');

        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        /*
         | The platform-wide catalog. An admin may add a product to an approved shop —
         | editing and removing one stays with the vendor who has to supply it.
         */
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');

        // Oversight only. Advancing a vendor order stays with the vendor who delivers it.
        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');

        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::patch('customers/{user}/status', [CustomerController::class, 'updateStatus'])->name('customers.status');

        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    });
