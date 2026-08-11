<?php

declare(strict_types=1);

use App\Enums\ReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Ties the review to a specific delivered purchase, which is what makes it
            // verifiable and enforces one review per purchased item.
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->string('status')->default(ReviewStatus::Pending->value);
            $table->timestamps();

            $table->unique('order_item_id');
            $table->index(['product_id', 'status']);
            $table->index('user_id');
        });
    }
};
