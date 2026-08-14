<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An index for the catalog's default sort, which had none it could use.
     *
     * Every storefront listing runs ListSellableProducts, whose default ordering is
     * `published_at DESC, id DESC`. The existing products_catalog_index is
     * (status, category_id, published_at): MySQL can only use a prefix of that up to
     * the first non-equality column, so the moment no category is filtered on — the
     * unfiltered /shop page, and every vendor shop page — category_id is skipped and
     * published_at is unreachable for ordering.
     *
     * The result was a filesort over every published product on the site to return
     * 24 of them, growing with the catalog rather than with the page size. This index
     * leads with the equality column and then the sort columns in sort order, so the
     * same query walks the index backwards and stops after 24 rows.
     *
     * `id` is included because it is the tie-breaker every sort carries, so the index
     * satisfies the ORDER BY outright instead of leaving a residual sort on ties.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['status', 'published_at', 'id'], 'products_published_sort_index');
        });
    }
};
