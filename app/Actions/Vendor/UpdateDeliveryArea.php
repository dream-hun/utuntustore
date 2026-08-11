<?php

declare(strict_types=1);

namespace App\Actions\Vendor;

use App\Models\VendorDeliveryArea;
use Illuminate\Validation\ValidationException;

/**
 * Changes the terms of an area a vendor already covers.
 *
 * The district and sector are deliberately immutable: moving a row to another
 * district is indistinguishable from removing it and adding a new one, and keeping
 * them fixed means the sector-belongs-to-district invariant cannot be broken by an
 * edit.
 *
 * Changing a fee never touches a historical order — checkout copies the fee onto the
 * vendor order at the time of purchase.
 */
final readonly class UpdateDeliveryArea
{
    /**
     * @throws ValidationException
     */
    public function handle(
        VendorDeliveryArea $area,
        int $deliveryFee,
        int $estimatedDaysMin,
        int $estimatedDaysMax,
        bool $isActive,
    ): VendorDeliveryArea {
        if ($estimatedDaysMax < $estimatedDaysMin) {
            throw ValidationException::withMessages([
                'estimated_days_max' => __('The longest estimate cannot be shorter than the shortest one.'),
            ]);
        }

        $area->update([
            'delivery_fee' => $deliveryFee,
            'estimated_days_min' => $estimatedDaysMin,
            'estimated_days_max' => $estimatedDaysMax,
            'is_active' => $isActive,
        ]);

        return $area;
    }
}
