<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Support\Settings;

/**
 * Writes the runtime platform configuration.
 *
 * These values are read at the moment a subscription is created and copied onto that
 * row, so changing the fee here never rewrites a single historical subscription
 * (BR-11). A new price applies to the next payment recorded and to renewals — the
 * revenue ledger keeps saying what each vendor actually paid.
 */
final readonly class UpdatePlatformSettings
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  array{
     *     vendor_subscription_fee: int,
     *     vendor_subscription_currency: string,
     *     vendor_subscription_days: int,
     *     vendor_subscription_grace_days: int
     * }  $values
     */
    public function handle(array $values): void
    {
        $this->settings->setMany($values);
    }
}
