<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ResolveProductVendor;
use App\Actions\Vendor\CreateProduct;
use App\Actions\Vendor\DeleteProduct;
use App\Actions\Vendor\SetProductPublication;
use App\Actions\Vendor\UpdateProduct;
use App\Enums\ProductStatus;
use App\Enums\VendorStatus;
use App\Http\Controllers\Concerns\PresentsProducts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
use App\Http\Requests\Admin\ProductUpdateRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The whole platform's catalog, and the one place an admin can add to it.
 *
 * Leaving the shop blank uses the admin's store, created on demand as platform-owned.
 * Editing cannot change a product's shop.
 *
 * Publishing an existing product stays with the vendor: it is the act of putting stock
 * in front of a buyer, and it answers to that shop's own selling eligibility.
 */
final class ProductController extends Controller
{
    use PresentsProducts;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = mb_trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $category = (string) $request->query('category', '');
        $vendorUuid = (string) $request->query('vendor', '');

        return Inertia::render('admin/products/index', [
            // Inside the closure: a partial reload that only wants a picker must not
            // pay for the paginator's count, select and eager loads.
            'products' => fn (): LengthAwarePaginator => $this->paginate($search, $status, $category, $vendorUuid),

            'filters' => [
                'search' => $search,
                'status' => $status,
                'category' => $category,
                'vendor' => $vendorUuid,
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

            // Only approved shops: a pending or rejected application has no catalog to
            // add to, which is also what the store request enforces.
            'vendors' => Inertia::defer(fn (): array => Vendor::query()
                ->where('status', VendorStatus::Approved)
                ->orderBy('shop_name')
                ->get()
                ->map(fn (Vendor $vendor): array => [
                    'id' => $vendor->uuid,
                    'shop_name' => $vendor->shop_name,
                    'can_sell' => $vendor->canSell(),
                ])
                ->all()),
        ]);
    }

    /**
     * Created as a draft, like a vendor's own product, and published in the same
     * request when the operator asked for it and the shop may sell.
     */
    public function store(
        ProductStoreRequest $request,
        ResolveProductVendor $resolveProductVendor,
        CreateProduct $createProduct,
        SetProductPublication $setProductPublication,
    ): RedirectResponse {
        $this->authorize('create', Product::class);

        $vendor = $resolveProductVendor->handle($this->currentUser($request), $request->vendor());

        $product = $createProduct->handle($vendor, $request->attributesForProduct(), $request->images());

        if ($request->shouldPublish()) {
            $setProductPublication->handle($product, true);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->shouldPublish()
                ? __('Product added to :shop and published.', ['shop' => $vendor->shop_name])
                : __('Product added to :shop as a draft.', ['shop' => $vendor->shop_name]),
        ]);

        return back();
    }

    /**
     * Images are appended to the gallery rather than replacing it, exactly as on the
     * vendor's own form.
     */
    public function update(ProductUpdateRequest $request, Product $product, UpdateProduct $updateProduct): RedirectResponse
    {
        $this->authorize('update', $product);

        $updateProduct->handle($product, $request->attributesForProduct(), $request->images());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return back();
    }

    /**
     * A product somebody has already bought is archived rather than deleted, so the
     * customer's order keeps its link back to the product page and its reviews.
     */
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
     * @return LengthAwarePaginator<int, non-empty-array<string, mixed>>
     */
    private function paginate(string $search, string $status, string $category, string $vendorUuid): LengthAwarePaginator
    {
        return Product::query()
            ->with(['category', 'media', 'vendor:id,uuid,shop_name'])
            ->withCount('variants')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when(
                ProductStatus::tryFrom($status) instanceof ProductStatus,
                fn (Builder $query) => $query->where('status', $status),
            )
            ->when($category !== '', fn (Builder $query) => $query->whereHas(
                'category',
                fn (Builder $query) => $query->where('uuid', $category),
            ))
            ->when($vendorUuid !== '', fn (Builder $query) => $query->whereHas(
                'vendor',
                fn (Builder $query) => $query->where('uuid', $vendorUuid),
            ))
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                ...$this->productRow($product),
                'vendor' => [
                    'id' => $product->vendor->uuid,
                    'shop_name' => $product->vendor->shop_name,
                ],
            ]);
    }
}
