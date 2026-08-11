<?php

declare(strict_types=1);

use App\Enums\VendorSubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The platform's revenue ledger, and the only money the database accounts for.
     *
     * Rows are never overwritten or hard-deleted; a subscription that ends is expired
     * or cancelled so the revenue history stays intact.
     */
    public function up(): void
    {
        Schema::create('vendor_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();

            // Copied from the configured fee at creation time so a later price change
            // never rewrites historical revenue.
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('RWF');

            $table->string('status')->default(VendorSubscriptionStatus::Pending->value);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('payment_method');
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('ends_at');
            $table->index('paid_at');
            $table->index(['vendor_id', 'status']);
        });
    }
};
