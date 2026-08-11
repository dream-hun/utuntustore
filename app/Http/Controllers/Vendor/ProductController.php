<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Actions\Vendor\CreateProduct;
use App\Actions\Vendor\DeleteProduct;
use App\Actions\Vendor\UpdateProduct;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A vendor's own catalog.
 *
 * Every query starts from the current vendor's id, so there is no filter, sort or page
 * number that could surface another shop's product. The policy repeats the check on
 * the single-product routes for the same reason.
 *
 * Creating and publishing carry `vendor.can-sell`; editing, unpublishing and deleting
 * deliberately do not. An expired vendor keeps their catalog and can still tidy it.
 */
final class ProductController extends Controller
{
    public function index(Request $request, Vendor $vendor): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = mb_trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $category = (string) $request->query('category', '');

        $products = Product::query()
            ->where('vendor_id', $vendor->id)
            ->with(['category', 'media'])
            ->withCount('variants')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when(
                in_array($status, array_column(ProductStatus::cases(), 'value'), true),
                fn (Builder $query) => $query->where('status', $status),
            )
            ->when($category !== '', fn (Builder $query) => $query->whereHas(
                'category',
                fn (Builder $query) => $query->where('uuid', $category),
            ))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product): array => $this->productRow($product));

        return Inertia::render('vendor/products/index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category' => $category,
            ],
            'categories' => Inertia::defer(fn (): array => Category::query()
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => [
                    'id' => $category->uuid,
                    'name' => $category->name,
                ])
                ->all()),
        ]);
    }

    public function store(ProductRequest $request, Vendor $vendor, CreateProduct $createProduct): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $createProduct->handle($vendor, $request->attributesForProduct(), $request->images());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created as a draft. Publish it when you are ready.')]);

        return back();
    }

    public function update(ProductRequest $request, Product $product, UpdateProduct $updateProduct): RedirectResponse
    {
        $this->authorize('update', $product);

        $updateProduct->handle($product, $request->attributesForProduct(), $request->images());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return back();
    }

    public function destroy(Product $product, DeleteProduct $deleteProduct): RedirectResponse
    {
        $this->authorize('delete', $product);

        $archived = $deleteProduct->handle($product);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $archived
                ? __('Product archived. It has been ordered before, so its history is kept.')
                : __('Product deleted.'),
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function productRow(Product $product): array
    {
        return [
            'id' => $product->uuid,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'price' => $product->price,
            'compare_at_price' => $product->compare_at_price,
            'currency' => $product->currency,
            'stock_quantity' => $product->stock_quantity,
            'low_stock_threshold' => $product->low_stock_threshold,
            'weight' => $product->weight,
            'status' => $product->status->value,
            'published_at' => $product->published_at,
            'is_low_stock' => $product->isLowStock(),
            'variants_count' => Cast::int($product->getAttribute('variants_count')),
            'category' => [
                'id' => $product->category->uuid,
                'name' => $product->category->name,
            ],
            'images' => $product->getMedia('images')
                ->map(fn (Media $media): array => [
                    'id' => $media->uuid,
                    'url' => $media->getUrl(),
                    'thumb_url' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
                ])
                ->all(),
        ];
    }
}
