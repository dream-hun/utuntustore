<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Storefront\ListSellableProducts;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\PresentsCatalog;
use App\Http\Requests\Storefront\CatalogFilterRequest;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorDeliveryArea;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A vendor's public shop page.
 *
 * A vendor who cannot sell — unapproved, suspended, or past their subscription's
 * grace period — 404s here. Their shop disappears from the storefront while every
 * row they own stays exactly where it was.
 */
final class VendorController extends Controller
{
    use PresentsCatalog;

    public function __invoke(CatalogFilterRequest $request, Vendor $vendor, ListSellableProducts $products): Response
    {
        abort_unless($vendor->canSell(), 404);

        $vendor->load('media');

        $paginator = $products->handle([
            ...$request->catalogFilters(),
            'vendor_id' => $vendor->id,
        ]);

        return Inertia::render('storefront/vendor', [
            'vendor' => [
                ...$this->vendorCard($vendor),
                'description' => $vendor->description,
                'delivery_notes' => $vendor->delivery_notes,
                'banner_url' => $vendor->getFirstMediaUrl('banner', 'web') ?: null,
                'phone' => $vendor->phone,
                'email' => $vendor->email,
            ],

            'products' => $paginator->through(fn (Product $product): array => $this->productCard($product)),

            'filters' => [
                'search' => $request->input('search'),
                'sort' => $request->input('sort') ?? 'newest',
            ],

            // Where this shop delivers and what it charges: the single most useful
            // thing on the page for a customer deciding whether to order at all.
            'deliveryAreas' => Inertia::defer(fn (): array => $vendor->deliveryAreas()
                ->where('is_active', true)
                ->with(['district', 'sector'])
                ->get()
                ->sortBy(fn (VendorDeliveryArea $area): string => $area->district->name.' '.($area->sector->name ?? ''))
                ->map(fn (VendorDeliveryArea $area): array => [
                    'id' => $area->uuid,
                    'district' => $area->district->name,
                    'sector' => $area->sector?->name,
                    'delivery_fee' => $area->delivery_fee,
                    'estimated_days_min' => $area->estimated_days_min,
                    'estimated_days_max' => $area->estimated_days_max,
                ])
                ->values()
                ->all()),
        ]);
    }
}
