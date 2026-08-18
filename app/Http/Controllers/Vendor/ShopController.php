<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Actions\Vendor\UpdateShopProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\UpdateShopRequest;
use App\Models\Vendor;
use App\Support\MediaUrl;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The shop profile a customer reads before agreeing to pay cash at the door.
 *
 * Editing stays open to an expired vendor: the shop is already hidden from the
 * storefront, and keeping the profile accurate is part of getting back on it.
 */
final class ShopController extends Controller
{
    public function edit(Vendor $vendor): Response
    {
        $this->authorize('update', $vendor);

        return Inertia::render('vendor/shop', [
            'shop' => [
                'id' => $vendor->uuid,
                'shop_name' => $vendor->shop_name,
                'slug' => $vendor->slug,
                'description' => $vendor->description,
                'phone' => $vendor->phone,
                'email' => $vendor->email,
                'delivery_notes' => $vendor->delivery_notes,
                'status' => $vendor->status->value,
                'logo_url' => MediaUrl::fromCollection($vendor, 'logo', 'thumb'),
                'banner_url' => MediaUrl::fromCollection($vendor, 'banner', 'web'),
            ],
        ]);
    }

    public function update(UpdateShopRequest $request, Vendor $vendor, UpdateShopProfile $updateShopProfile): RedirectResponse
    {
        $this->authorize('update', $vendor);

        $updateShopProfile->handle(
            $vendor,
            $request->profile(),
            $request->logo(),
            $request->banner(),
            (bool) $request->validated('remove_logo', false),
            (bool) $request->validated('remove_banner', false),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Shop profile updated.')]);

        return to_route('vendor.shop.edit');
    }
}
