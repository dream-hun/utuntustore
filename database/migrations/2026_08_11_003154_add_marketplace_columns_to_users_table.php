<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid()->nullable()->after('id');
            $table->string('phone')->nullable()->after('email');
            $table->string('role')->default(UserRole::Customer->value)->after('password');
            $table->string('status')->default(UserStatus::Active->value)->after('role');

            $table->index('role');
            $table->index('status');
        });

        DB::table('users')->whereNull('uuid')->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->uuid()->nullable(false)->unique()->change();
        });
    }
};
