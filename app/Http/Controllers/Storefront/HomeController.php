<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Storefront\PickFeaturedVendors;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\PresentsCatalog;
use App\Models\Product;
use App\Models\Vendor;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends Controller
{
    use PresentsCatalog;

    public function __invoke(PickFeaturedVendors $featuredVendors): Response
    {
        return Inertia::render('storefront/home', [
            'categories' => Inertia::defer(fn (): array => $this->navCategories()),

            'latestProducts' => Inertia::defer(fn (): array => Product::query()
                ->sellable()
                ->with(['vendor', 'media'])
                ->latest('published_at')
                ->limit(12)
                ->get()
                ->map(fn (Product $product): array => $this->productCard($product))
                ->all()),

            'featuredVendors' => Inertia::defer(fn (): array => $featuredVendors->handle()
                ->map(fn (Vendor $vendor): array => $this->vendorCard($vendor))
                ->all()),
        ]);
    }
}
