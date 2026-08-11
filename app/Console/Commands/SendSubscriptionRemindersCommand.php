<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Subscriptions\SweepSubscriptions;
use App\Models\Vendor;
use App\Notifications\SubscriptionExpiring;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Sends expiry reminders on the configured lead days (default 30 / 7 / 1).
 *
 * Idempotent for a given day: `expiringIn()` matches vendors whose subscription ends
 * within that one calendar day, so a repeated run on the same day re-notifies the
 * same small set rather than fanning out, and a missed day simply skips that notice.
 */
#[Description('Notify vendors whose subscription is about to expire')]
#[Signature('subscriptions:remind')]
final class SendSubscriptionRemindersCommand extends Command
{
    public function handle(SweepSubscriptions $sweep): int
    {
        /** @var array<int, int> $leadDays */
        $leadDays = config('marketplace.subscription.reminder_days');

        $sent = 0;

        foreach ($leadDays as $days) {
            $vendors = $sweep->expiringIn($days);

            foreach ($vendors as $vendor) {
                $this->notifyVendor($vendor, $days);
                $sent++;
            }

            $this->line("{$days} day notice: {$vendors->count()} vendor(s)");
        }

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }

    private function notifyVendor(Vendor $vendor, int $days): void
    {
        $vendor->loadMissing('user');

        $vendor->user->notify(new SubscriptionExpiring($vendor, $days));
    }
}
