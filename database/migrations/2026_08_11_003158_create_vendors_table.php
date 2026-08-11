<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Enums\VendorStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * There is deliberately no commission_rate column: the platform charges an annual
     * subscription instead of a percentage of sales.
     *
     * There are deliberately no bank or mobile money columns either. The platform never
     * sends money to a vendor, so storing payout details would create liability for data
     * it has no use for.
     */
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('shop_name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('status')->default(VendorStatus::Pending->value);
            $table->boolean('is_platform_owned')->default(false);

            // Denormalized from the current subscription so storefront eligibility is a
            // single indexed condition instead of a join. Maintained by the daily sweep.
            $table->string('subscription_status')->default(SubscriptionStatus::None->value);
            $table->timestamp('subscription_ends_at')->nullable();

            $table->text('delivery_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('subscription_status');
            $table->index('subscription_ends_at');

            // Storefront selling eligibility: status = approved AND subscription_status IN (active, grace).
            $table->index(['status', 'subscription_status'], 'vendors_selling_eligibility_index');
        });
    }
};
