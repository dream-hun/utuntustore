<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The table that makes marketplace isolation possible: a vendor may only ever query
     * rows where vendor_id matches their own vendor.
     *
     * There are no commission, vendor_earnings, collected_by, settled_at or payout_id
     * columns. The vendor collects `total` in cash on delivery, so the total already is
     * the money the vendor received. Nothing is owed, so nothing needs settling.
     */
    public function up(): void
    {
        Schema::create('vendor_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('order_number')->unique();

            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);

            // The vendor's own delivery charge, resolved from their delivery areas at
            // checkout and copied here so later fee changes never alter a past order.
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total');

            $table->string('status')->default(OrderStatus::Pending->value);

            // Vendor-reported, not a payment confirmation. Drives review eligibility
            // and sales reporting.
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'vendor_id']);
            $table->index('status');
            $table->index(['vendor_id', 'created_at']);
            $table->index(['vendor_id', 'delivered_at']);
            $table->index(['vendor_id', 'status']);
        });
    }
};
