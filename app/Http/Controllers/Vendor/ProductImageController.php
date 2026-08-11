<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Removing one photo from a product's gallery.
 *
 * The media row is looked up through the product's own collection rather than by id,
 * so a guessed identifier can only ever address an image the vendor already owns.
 */
final class ProductImageController extends Controller
{
    public function destroy(Product $product, string $media): RedirectResponse
    {
        $this->authorize('update', $product);

        $image = $product->getMedia('images')->firstWhere('uuid', $media);

        abort_if($image === null, 404);

        $image->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image removed.')]);

        return back();
    }
}
