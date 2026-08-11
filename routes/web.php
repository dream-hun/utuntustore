<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Post-login Entry Point
|--------------------------------------------------------------------------
|
| Fortify's auth flows all redirect to `dashboard`, which fans out to the right
| area for the signed-in user's role.
|
*/

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/storefront.php';
require __DIR__.'/account.php';
require __DIR__.'/vendor.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
