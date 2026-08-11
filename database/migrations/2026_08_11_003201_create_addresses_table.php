<?php

declare(strict_types=1);

use App\Enums\AddressType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * There is no postal_code column: Rwanda has no postal code in daily use, so the
     * field would be permanently empty. The landmark carries that weight instead —
     * it is usually what actually gets a delivery to the door.
     */
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default(AddressType::Shipping->value);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('country', 2)->default('RW');

            // Required and indexed: these are what delivery-coverage matching runs on.
            $table->foreignId('district_id')->constrained()->restrictOnDelete();
            $table->foreignId('sector_id')->constrained()->restrictOnDelete();

            $table->string('cell')->nullable();
            $table->string('village')->nullable();
            $table->string('address_line')->nullable();
            $table->string('landmark')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['district_id', 'sector_id'], 'addresses_coverage_index');
            $table->index(['user_id', 'is_default']);
        });
    }
};
