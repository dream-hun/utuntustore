<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Subscriptions\SweepSubscriptions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily maintenance of vendor selling eligibility.
 *
 * Safe to run repeatedly: the sweep derives each vendor's state from the clock rather
 * than stepping it forward, so a missed or duplicated run cannot leave an expired shop
 * selling or double-expire a vendor who has already paid.
 */
#[Description('Move vendor subscriptions through active, grace and expired states')]
#[Signature('subscriptions:sweep')]
final class SweepSubscriptionsCommand extends Command
{
    public function handle(SweepSubscriptions $sweep): int
    {
        $counts = $sweep->handle();

        $this->table(
            ['State', 'Vendors'],
            [
                ['Active', $counts['active']],
                ['Grace', $counts['grace']],
                ['Expired', $counts['expired']],
            ],
        );

        return self::SUCCESS;
    }
}
