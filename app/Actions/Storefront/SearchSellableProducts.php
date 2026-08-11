<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Models\Product;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies a catalog search term to a product query.
 *
 * MySQL carries a FULLTEXT index over name, short_description and description, so
 * production searches go through it. SQLite — which the test suite runs on — has no
 * such index, so the same search falls back to LIKE there rather than failing. Both
 * paths are real: the fallback is what the tests exercise.
 *
 * @see database/migrations/2026_08_11_003203_create_products_table.php
 */
final readonly class SearchSellableProducts
{
    /**
     * @param  Builder<Product>  $query
     */
    public function handle(Builder $query, ?string $term): void
    {
        $term = mb_trim((string) $term);

        if ($term === '') {
            return;
        }

        $connection = $query->getConnection();

        // Only the MySQL schema carries the FULLTEXT index (see the products
        // migration); SQLite, which the test suite runs on, falls through to LIKE.
        if ($connection instanceof Connection && $connection->getDriverName() === 'mysql') {
            $query->whereFullText(['name', 'short_description', 'description'], $term);

            return;
        }

        // Escape the LIKE wildcards so a customer typing "50%" searches for that
        // literal string instead of matching every row.
        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('name', 'like', $like)
                ->orWhere('short_description', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }
}
