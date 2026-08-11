<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product images are handled by spatie/laravel-medialibrary rather than a
     * product_images table, which gives conversions and responsive images for free.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('short_description')->nullable();
            $table->string('sku')->nullable();

            // Whole RWF. RWF has no minor unit in practice, so integers avoid rounding
            // entirely and splitting an order across vendors never produces fractions.
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('compare_at_price')->nullable();
            $table->string('currency', 3)->default('RWF');

            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->unsignedInteger('weight')->nullable();
            $table->string('status')->default(ProductStatus::Draft->value);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['vendor_id', 'sku']);
            $table->index('status');
            $table->index('created_at');

            // Storefront catalog: published products of a given category, newest first.
            $table->index(['status', 'category_id', 'published_at'], 'products_catalog_index');
            $table->index(['vendor_id', 'status']);
        });

        // Catalog search starts as a database search per docs/06-system-architecture.md
        // section 14. SQLite (used by the test suite) has no FULLTEXT index, so the
        // search Action falls back to LIKE there.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('products', function (Blueprint $table): void {
                $table->fullText(['name', 'short_description', 'description']);
            });
        }
    }
};
