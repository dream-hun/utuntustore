<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Actions\Vendor\AdjustStock;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\AdjustStockRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock across a vendor's products and their variants, adjustable in place.
 *
 * A vendor delivers their own orders out of their own stock room, so this screen is
 * how the catalog is kept honest — an oversold product is a doorstep argument, not a
 * refund the platform can process.
 */
final class InventoryController extends Controller
{
    public function index(Request $request, Vendor $vendor): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = mb_trim((string) $request->query('search', ''));
        $onlyLowStock = $request->boolean('low_stock');

        $products = Product::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', '!=', ProductStatus::Archived)
            ->with(['variants' => fn (Relation $query) => $query->orderBy('name')])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when($onlyLowStock, fn (Builder $query) => $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold'))
            ->orderBy('stock_quantity')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $product): array => [
                'id' => $product->uuid,
                'name' => $product->name,
                'sku' => $product->sku,
                'status' => $product->status->value,
                'stock_quantity' => $product->stock_quantity,
                'low_stock_threshold' => $product->low_stock_threshold,
                'is_low_stock' => $product->isLowStock(),
                'variants' => $product->variants
                    ->map(fn (ProductVariant $variant): array => [
                        'id' => $variant->uuid,
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'price' => $variant->price,
                        'stock_quantity' => $variant->stock_quantity,
                        'is_active' => $variant->is_active,
                    ])
                    ->all(),
            ]);

        return Inertia::render('vendor/inventory', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'low_stock' => $onlyLowStock,
            ],
        ]);
    }

    public function updateProduct(AdjustStockRequest $request, Product $product, AdjustStock $adjustStock): RedirectResponse
    {
        $this->authorize('update', $product);

        $adjustStock->handle($product, $request->integer('stock_quantity'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock updated for :product.', ['product' => $product->name])]);

        return back();
    }

    public function updateVariant(AdjustStockRequest $request, ProductVariant $variant, AdjustStock $adjustStock): RedirectResponse
    {
        $this->authorize('update', $variant);

        $adjustStock->handle($variant, $request->integer('stock_quantity'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock updated for :variant.', ['variant' => $variant->name])]);

        return back();
    }
}
