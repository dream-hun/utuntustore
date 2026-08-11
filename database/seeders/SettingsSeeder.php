<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

/**
 * Seeds the platform configuration an admin can change without a deployment.
 *
 * These mirror the defaults in config/marketplace.php. Writing them into the table
 * up front means the admin settings screen has real values to show on a fresh
 * install rather than empty fields backed by invisible config fallbacks.
 */
final class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = resolve(Settings::class);

        $settings->setMany([
            'vendor_subscription_fee' => Config::integer('marketplace.subscription.fee'),
            'vendor_subscription_currency' => Config::string('marketplace.currency'),
            'vendor_subscription_days' => Config::integer('marketplace.subscription.days'),
            'vendor_subscription_grace_days' => Config::integer('marketplace.subscription.grace_days'),
        ]);
    }
}
