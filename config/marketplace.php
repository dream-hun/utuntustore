<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The MVP is single-currency. RWF has no minor unit in daily use, so every
    | amount in the system is a whole number of francs stored as a big integer.
    | Carrying the code on carts, orders and subscriptions means a second
    | currency can be introduced later without a schema rewrite.
    |
    */

    'currency' => env('MARKETPLACE_CURRENCY', 'RWF'),

    /*
    |--------------------------------------------------------------------------
    | Vendor Subscription
    |--------------------------------------------------------------------------
    |
    | The platform's only revenue. These are the fallback defaults; an admin can
    | override them at runtime through the settings table without a deployment.
    | The amount is copied onto each subscription row at creation time so a later
    | price change never rewrites historical revenue.
    |
    */

    'subscription' => [
        'fee' => (int) env('MARKETPLACE_SUBSCRIPTION_FEE', 20_000),
        'days' => (int) env('MARKETPLACE_SUBSCRIPTION_DAYS', 365),
        'grace_days' => (int) env('MARKETPLACE_SUBSCRIPTION_GRACE_DAYS', 7),

        /*
         | Days before expiry on which a reminder is sent to the vendor.
         */
        'reminder_days' => [30, 7, 1],
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog
    |--------------------------------------------------------------------------
    */

    'catalog' => [
        'per_page' => 24,
        'max_cart_quantity' => 99,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    |
    | How long a guest cart survives before the cleanup job prunes it.
    |
    */

    'cart' => [
        'guest_lifetime_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bootstrap Admin
    |--------------------------------------------------------------------------
    |
    | The first admin account, created by DatabaseSeeder on a fresh install.
    | Without one there is no way into the admin area, and recording a vendor's
    | subscription payment — the platform's only revenue event — is an admin
    | action. A real deployment sets these; the fallbacks exist so that
    | `migrate:fresh --seed` gives a working local login.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Platform Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@shop.test'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
