<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Config;

final readonly class ResolveProductVendor
{
    public function handle(User $admin, ?Vendor $vendor): Vendor
    {
        return $vendor ?? Vendor::query()->firstOrCreate(
            ['user_id' => $admin->id],
            [
                'shop_name' => Config::string('app.name'),
                'slug' => 'platform-store-'.$admin->uuid,
                'phone' => $admin->phone ?? '',
                'email' => $admin->email,
                'status' => VendorStatus::Approved,
                'is_platform_owned' => true,
                'approved_at' => now(),
            ],
        );
    }
}
