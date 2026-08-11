<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The denormalized selling-eligibility state stored on the vendor row.
 *
 * This is derived from the vendor's current subscription by the daily sweep
 * so storefront queries can filter on a single indexed column.
 */
enum SubscriptionStatus: string
{
    case None = 'none';
    case Active = 'active';
    case Grace = 'grace';
    case Expired = 'expired';

    /**
     * Whether this state still permits the vendor to sell.
     */
    public function permitsSelling(): bool
    {
        return in_array($this, [self::Active, self::Grace], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::None => 'No subscription',
            self::Active => 'Active',
            self::Grace => 'In grace period',
            self::Expired => 'Expired',
        };
    }
}
