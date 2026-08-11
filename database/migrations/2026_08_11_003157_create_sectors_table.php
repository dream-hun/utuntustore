<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('district_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();

            // Sector names repeat across districts, so uniqueness is only ever per district.
            $table->unique(['district_id', 'name']);
        });
    }
};
