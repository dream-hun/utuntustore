<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\VendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
|
| Public, guest-accessible pages. Everything served here is filtered by vendor
| selling eligibility: a vendor whose subscription has lapsed disappears from
| the catalog without any of their data being touched.
|
| The cart is deliberately open to guests — nobody should have to register
| before they have decided to buy. Only checkout requires an account, because
| an order needs a delivery address and someone to hand the cash to.
|
*/

Route::get('/', HomeController::class)->name('home');

Route::get('shop', CatalogController::class)->name('shop');
Route::get('products/{product:slug}', ProductController::class)->name('products.show');
Route::get('categories/{category:slug}', CategoryController::class)->name('categories.show');
Route::get('vendors/{vendor:slug}', VendorController::class)->name('vendors.show');

Route::controller(CartController::class)
    ->prefix('cart')
    ->name('cart.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('{item}', 'update')->name('update');
        Route::delete('{item}', 'destroy')->name('destroy');
    });

Route::middleware(['auth', 'verified'])
    ->controller(CheckoutController::class)
    ->prefix('checkout')
    ->name('checkout.')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('addresses', 'storeAddress')->name('addresses.store');
        Route::get('confirmation/{order}', 'confirmation')->name('confirmation');
    });
