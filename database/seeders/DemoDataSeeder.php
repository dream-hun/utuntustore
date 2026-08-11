<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Subscriptions\RecordSubscriptionPayment;
use App\Enums\SubscriptionPaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\Address;
use App\Models\Category;
use App\Models\District;
use App\Models\Product;
use App\Models\Sector;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local development data: a browsable storefront with real Rwandan geography.
 *
 * Deliberately NOT called from DatabaseSeeder — run it explicitly with
 * `php artisan db:seed --class=DemoDataSeeder` so it can never reach production.
 *
 * It intentionally includes one expired vendor, because the single easiest thing to
 * get wrong in this marketplace is a lapsed shop still appearing in the catalog.
 * With this data loaded, that bug is visible on the home page rather than hidden.
 */
final class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (District::query()->count() === 0) {
            $this->call(RwandaLocationSeeder::class);
        }

        $categories = $this->createCategories();

        $gasabo = District::query()->where('name', 'Gasabo')->firstOrFail();
        $kicukiro = District::query()->where('name', 'Kicukiro')->firstOrFail();

        $this->createVendor('Kigali Electronics', $gasabo, 2000, $categories, SubscriptionStatus::Active);
        $this->createVendor('Nyabugogo Fashion', $gasabo, 1500, $categories, SubscriptionStatus::Active);
        $this->createVendor('Kicukiro Home Goods', $kicukiro, 0, $categories, SubscriptionStatus::Active);

        // The cautionary case: approved, has published products, but lapsed. None of
        // its catalog may appear on the storefront.
        $this->createVendor('Lapsed Corner Shop', $gasabo, 1000, $categories, SubscriptionStatus::Expired);

        $this->createCustomer($gasabo);

        $this->command->info('Demo data seeded. Sign in as customer@shop.test / password.');
    }

    /**
     * @return array<int, Category>
     */
    private function createCategories(): array
    {
        $names = ['Electronics', 'Fashion', 'Home & Living', 'Food & Drink'];

        return array_map(
            fn (string $name): Category => Category::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            ),
            $names,
        );
    }

    /**
     * @param  array<int, Category>  $categories
     */
    private function createVendor(
        string $shopName,
        District $district,
        int $deliveryFee,
        array $categories,
        SubscriptionStatus $subscriptionStatus,
    ): void {
        $slug = Str::slug($shopName);

        if (Vendor::query()->where('slug', $slug)->exists()) {
            return;
        }

        $user = User::factory()->create([
            'name' => $shopName.' Owner',
            'email' => $slug.'@shop.test',
            'role' => UserRole::Vendor,
            'email_verified_at' => now(),
        ]);

        $vendor = Vendor::factory()->for($user)->create([
            'shop_name' => $shopName,
            'slug' => $slug,
            'status' => VendorStatus::Approved,
            'approved_at' => now(),
            'subscription_status' => SubscriptionStatus::None,
        ]);

        // Go through the real action so the demo data exercises the same revenue path
        // production does, rather than hand-writing a subscription row.
        resolve(RecordSubscriptionPayment::class)->handle(
            $vendor,
            User::query()->where('role', UserRole::Admin)->firstOrFail(),
            SubscriptionPaymentMethod::MobileMoney,
            'DEMO-'.Str::upper(Str::random(6)),
        );

        if ($subscriptionStatus === SubscriptionStatus::Expired) {
            $vendor->update([
                'subscription_status' => SubscriptionStatus::Expired,
                'subscription_ends_at' => now()->subDays(60),
            ]);
        }

        // District-wide coverage, refined with a cheaper rate for one sector — the
        // exact shape the coverage resolver is built to handle.
        VendorDeliveryArea::factory()->create([
            'vendor_id' => $vendor->id,
            'district_id' => $district->id,
            'sector_id' => null,
            'delivery_fee' => $deliveryFee,
            'is_active' => true,
        ]);

        $sector = Sector::query()->where('district_id', $district->id)->first();

        if ($sector !== null && $deliveryFee > 0) {
            VendorDeliveryArea::factory()->create([
                'vendor_id' => $vendor->id,
                'district_id' => $district->id,
                'sector_id' => $sector->id,
                'delivery_fee' => intdiv($deliveryFee, 2),
                'is_active' => true,
            ]);
        }

        foreach ($categories as $category) {
            Product::factory()
                ->count(3)
                ->for($vendor)
                ->for($category)
                ->published()
                ->create();
        }

        // One draft per shop, to prove drafts stay off the storefront.
        Product::factory()->for($vendor)->for($categories[0])->create();
    }

    private function createCustomer(District $district): void
    {
        if (User::query()->where('email', 'customer@shop.test')->exists()) {
            return;
        }

        $customer = User::factory()->create([
            'name' => 'Demo Customer',
            'email' => 'customer@shop.test',
            'role' => UserRole::Customer,
            'email_verified_at' => now(),
        ]);

        Address::factory()->for($customer)->create([
            'district_id' => $district->id,
            'sector_id' => Sector::query()->where('district_id', $district->id)->firstOrFail()->id,
            'is_default' => true,
        ]);
    }
}
