<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Actions\Storefront\PickFeaturedVendors;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\PresentsCatalog;
use App\Models\Category;
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
            'categories' => Inertia::defer(fn (): array => Category::query()
                ->active()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->limit(12)
                ->get()
                ->map(fn (Category $category): array => $this->categoryLink($category))
                ->all()),

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
