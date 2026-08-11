<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\VendorStatus;
use App\Models\Vendor;

/**
 * Approves, rejects, suspends or reinstates a vendor.
 *
 * Suspension is the platform's only real recourse in a dispute. Because it never held
 * the customer's money there is no refund to issue and no transaction to reverse — the
 * most it can do is take the shop off the storefront. Nothing here touches the vendor's
 * products, orders or subscription: moderation changes what a shop may do, never what
 * it owns, so reinstating restores the catalog exactly as it was.
 */
final readonly class ModerateVendor
{
    public function handle(Vendor $vendor, VendorStatus $status): Vendor
    {
        $vendor->status = $status;

        if ($status === VendorStatus::Approved) {
            // First approval stamps the date; a later reinstatement keeps the original,
            // because approved_at records when the shop was first let onto the platform.
            $vendor->approved_at ??= now();
        }

        $vendor->save();

        return $vendor;
    }
}
