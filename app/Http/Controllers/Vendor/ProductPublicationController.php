<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Actions\Vendor\SetProductPublication;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Publishing is the one catalog action that requires a live subscription, because it
 * is the moment inventory is put in front of a customer.
 *
 * Unpublishing is the mirror image and is never gated — a vendor must always be able
 * to take down a product they can no longer supply.
 */
final class ProductPublicationController extends Controller
{
    public function store(Product $product, SetProductPublication $setProductPublication): RedirectResponse
    {
        $this->authorize('publish', $product);

        $setProductPublication->handle($product, true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product published.')]);

        return back();
    }

    public function destroy(Product $product, SetProductPublication $setProductPublication): RedirectResponse
    {
        $this->authorize('update', $product);

        $setProductPublication->handle($product, false);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Product unpublished and back to draft.')]);

        return back();
    }
}
