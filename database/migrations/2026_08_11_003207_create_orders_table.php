<?php

declare(strict_types=1);

use App\Enums\OrderPaymentMethod;
use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * There is intentionally no payments table. An order total is a record of what the
     * customer agreed to hand the vendor at the door, never a balance the platform holds.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('order_number')->unique();
            $table->string('status')->default(OrderStatus::Pending->value);
            $table->string('currency', 3)->default('RWF');

            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);

            // Must equal the sum of its vendor orders' shipping fees.
            $table->unsignedBigInteger('shipping_fee')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->unsignedBigInteger('total');

            $table->string('payment_method')->default(OrderPaymentMethod::CashOnDelivery->value);
            $table->foreignId('shipping_address_id')->constrained('addresses')->restrictOnDelete();
            $table->foreignId('billing_address_id')->nullable()->constrained('addresses')->restrictOnDelete();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
        });
    }
};
