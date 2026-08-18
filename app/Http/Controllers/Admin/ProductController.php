<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Vendor\CreateProduct;
use App\Actions\Vendor\SetProductPublication;
use App\Enums\ProductStatus;
use App\Enums\VendorStatus;
use App\Http\Controllers\Concerns\PresentsProducts;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductStoreRequest;
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
 * A product always belongs to a shop — there is no such thing as a product the
 * platform sells directly — so adding one starts by choosing the vendor it is for.
 * That is the only field this form has that a vendor's own form does not.
 *
 * Editing, deleting and unpublishing stay with the vendor who has to supply the goods,
 * matching how admin oversight of orders already works.
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
        CreateProduct $createProduct,
        SetProductPublication $setProductPublication,
    ): RedirectResponse {
        $this->authorize('create', Product::class);

        $vendor = $request->vendor();

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
