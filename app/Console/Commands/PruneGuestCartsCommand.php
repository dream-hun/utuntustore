<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Cart;
use App\Support\Cast;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Removes abandoned guest carts.
 *
 * Guest carts are keyed on a session id that is gone long before the row is, so
 * without this they accumulate forever. Only carts with no user are pruned — a
 * signed-in customer's cart is theirs to keep for as long as they want it.
 */
#[Description('Delete abandoned guest carts older than the configured lifetime')]
#[Signature('carts:prune')]
final class PruneGuestCartsCommand extends Command
{
    public function handle(): int
    {
        $days = Config::integer('marketplace.cart.guest_lifetime_days');

        $deleted = Cast::int(Cart::query()
            ->whereNull('user_id')
            ->where('updated_at', '<', now()->subDays($days))
            ->delete());

        $this->info("Pruned {$deleted} guest cart(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
