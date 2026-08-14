<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Storefront\Concerns\PresentsCatalog;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use Closure;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A single product's page.
 *
 * The product is re-checked against Product::sellable() rather than trusted from
 * route model binding: a lapsed vendor's product must 404 even for someone holding
 * a direct link to it.
 */
final class ProductController extends Controller
{
    use PresentsCatalog;

    public function __invoke(Product $product): Response
    {
        abort_unless(
            Product::query()->sellable()->whereKey($product->getKey())->exists(),
            404,
        );

        $product->load([
            'vendor.media',
            'category',
            'media',
            'variants' => fn (HasMany $query): HasMany => $query->where('is_active', true)->orderBy('name'),
        ]);

        /** @var Closure(): HasMany<Review, Product> $approvedReviews */
        $approvedReviews = fn (): HasMany => $product->reviews()
            ->where('status', ReviewStatus::Approved);

        return Inertia::render('storefront/product', [
            'navCategories' => Inertia::defer(fn (): array => $this->navCategories()),

            // Same category, any shop — on a marketplace the useful comparison is
            // "what else could I buy instead", which usually means another vendor.
            'related' => Inertia::defer(fn (): array => Product::query()
                ->sellable()
                ->where('category_id', $product->category_id)
                ->whereKeyNot($product->getKey())
                ->with(['vendor', 'media'])
                ->latest('published_at')
                ->limit(4)
                ->get()
                ->map(fn (Product $related): array => $this->productCard($related))
                ->all()),

            'product' => [
                ...$this->productCard($product),
                'description' => $product->description,
                'short_description' => $product->short_description,
                'sku' => $product->sku,
                'stock_quantity' => $product->stock_quantity,
                'is_low_stock' => $product->isLowStock(),
                'category' => $this->categoryLink($product->category),
                'images' => $product->getMedia('images')
                    ->map(fn (Media $media): array => [
                        'id' => (string) $media->uuid,
                        'thumb_url' => $media->getUrl('thumb'),
                        'web_url' => $media->getUrl('web'),
                        'alt' => $media->name,
                    ])
                    ->values()
                    ->all(),
                'variants' => $product->variants
                    ->map(fn (ProductVariant $variant): array => [
                        'id' => $variant->uuid,
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'price' => $variant->price,
                        'stock_quantity' => $variant->stock_quantity,
                    ])
                    ->all(),
            ],

            'vendor' => [
                ...$this->vendorCard($product->vendor),
                'description' => $product->vendor->description,
                'delivery_notes' => $product->vendor->delivery_notes,
            ],

            // One aggregate rather than a separate AVG and COUNT pass over the same
            // rows: this runs on every view of a product page.
            'rating' => $this->rating($approvedReviews()),

            // Reviews sit below the fold, so they load after the part of the page a
            // customer is actually waiting on.
            'reviews' => Inertia::defer(fn () => $approvedReviews()
                ->with('user')
                ->latest()
                ->paginate(5, ['*'], 'reviews')
                ->withQueryString()
                ->through(fn (Review $review): array => [
                    'id' => $review->uuid,
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'comment' => $review->comment,
                    'author_name' => $review->user->name,
                    'created_at' => $review->created_at,
                ])),
        ]);
    }

    /**
     * @param  HasMany<Review, Product>  $reviews
     * @return array{average: float, count: int}
     */
    private function rating(HasMany $reviews): array
    {
        $aggregate = $reviews
            ->toBase()
            ->selectRaw('COUNT(*) as review_count, COALESCE(AVG(rating), 0) as average_rating')
            ->first();

        $average = $aggregate?->average_rating;
        $count = $aggregate?->review_count;

        return [
            'average' => round(is_numeric($average) ? (float) $average : 0.0, 1),
            'count' => is_numeric($count) ? (int) $count : 0,
        ];
    }
}
