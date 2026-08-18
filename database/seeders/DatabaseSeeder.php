<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

/**
 * Note the deliberate absence of `WithoutModelEvents`: every model in this project
 * generates its public `uuid` in a `creating` hook via the HasUuid concern, so
 * muting model events makes each insert fail on the non-nullable uuid column.
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only reference data, platform settings and a first admin are seeded here —
     * everything a fresh install genuinely needs and nothing it does not. Demo
     * catalog data belongs in a separate, explicitly-invoked seeder so it can never
     * reach production by accident.
     */
    public function run(): void
    {
        $this->call([
            RwandaLocationSeeder::class,
            SettingsSeeder::class,
        ]);

        $this->seedFirstAdmin();
    }

    /**
     * Create the bootstrap admin account.
     *
     * Without one there is no way into the admin area, and recording a vendor's
     * subscription payment — the platform's only revenue event — is an admin action.
     *
     * The password comes from the environment so a real deployment sets its own; the
     * local fallback exists only so `migrate:fresh --seed` gives a working login.
     *
     * Every attribute is written out here rather than leaning on `User::factory()`,
     * because this is the one seeder a production deploy actually runs. Factories
     * call `fake()`, which lives in `fakerphp/faker` — a require-dev package absent
     * from any `composer install --no-dev` install. Going through the factory makes
     * this line fatal on a production host with
     * `Call to undefined function Database\Factories\fake()`, and the failure lands
     * after the reference-data seeders have already committed, so the database looks
     * half-seeded and simply has no way in. Keep this path free of `factory()`.
     */
    private function seedFirstAdmin(): void
    {
        $email = Config::string('marketplace.admin.email');

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        User::query()->create([
            'name' => Config::string('marketplace.admin.name'),
            'email' => $email,
            'password' => Config::string('marketplace.admin.password'),
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);
    }
}
