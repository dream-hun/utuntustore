<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A visitor has exactly one cart, and only the database can enforce it.
     *
     * ResolveCart finds-or-creates on user_id (or session_id for a guest). Two
     * requests arriving together — a double-tapped "add to cart" on a slow phone
     * connection, or a page and its partial reload — both find nothing and both
     * insert, leaving the customer with a basket split across two rows and one of
     * them invisible. There is no application-level fix for that; the constraint is
     * the fix, and firstOrCreate() already retries on a duplicate key.
     *
     * Both columns are nullable and both indexes tolerate that: a signed-in cart has
     * no session_id and a guest cart has no user_id, and MySQL and SQLite alike
     * exempt NULLs from a unique index.
     */
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropIndex(['session_id']);

            $table->unique('user_id');
            $table->unique('session_id');
        });
    }
};
