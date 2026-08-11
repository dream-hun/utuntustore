<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Updates the shop a customer sees before they commit to a cash-on-delivery order.
 *
 * The delivery notes and phone number matter more here than anywhere else in the
 * system: the vendor delivers the order themselves, so these are the customer's only
 * way to reach whoever will knock on their door.
 *
 * The shop slug is not editable — it is the public shop URL, and rewriting it would
 * break every link a vendor has already shared.
 */
final readonly class UpdateShopProfile
{
    /**
     * @param  array{shop_name: string, description: string|null, phone: string, email: string|null, delivery_notes: string|null}  $attributes
     */
    public function handle(
        Vendor $vendor,
        array $attributes,
        ?UploadedFile $logo = null,
        ?UploadedFile $banner = null,
        bool $removeLogo = false,
        bool $removeBanner = false,
    ): Vendor {
        return DB::transaction(function () use ($vendor, $attributes, $logo, $banner, $removeLogo, $removeBanner): Vendor {
            $vendor->update([
                'shop_name' => $attributes['shop_name'],
                'description' => $attributes['description'],
                'phone' => $attributes['phone'],
                'email' => $attributes['email'],
                'delivery_notes' => $attributes['delivery_notes'],
            ]);

            // Both collections are singleFile(), so adding replaces whatever was there.
            if ($logo instanceof UploadedFile) {
                $vendor->addMedia($logo)->toMediaCollection('logo');
            } elseif ($removeLogo) {
                $vendor->clearMediaCollection('logo');
            }

            if ($banner instanceof UploadedFile) {
                $vendor->addMedia($banner)->toMediaCollection('banner');
            } elseif ($removeBanner) {
                $vendor->clearMediaCollection('banner');
            }

            return $vendor->refresh();
        });
    }
}
