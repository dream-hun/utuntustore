<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a vendor delivers and what they charge.
     *
     * A null sector_id means the whole district; a set sector_id overrides the
     * district-wide row for that sector.
     */
    public function up(): void
    {
        Schema::create('vendor_delivery_areas', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->constrained()->restrictOnDelete();
            $table->foreignId('sector_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->unsignedSmallInteger('estimated_days_min')->default(1);
            $table->unsignedSmallInteger('estimated_days_max')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // MySQL does not treat NULLs as equal under a unique index, so the
            // district-wide row must also be guarded in application code.
            $table->unique(['vendor_id', 'district_id', 'sector_id'], 'vendor_delivery_areas_unique');

            // Coverage lookup at checkout and for storefront filtering.
            $table->index(['district_id', 'sector_id'], 'vendor_delivery_areas_coverage_index');
            $table->index(['vendor_id', 'is_active']);
        });
    }
};
