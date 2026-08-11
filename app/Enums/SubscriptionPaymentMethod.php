<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a vendor paid their subscription fee off-platform.
 *
 * The platform never processes these payments; an admin records them after the fact.
 */
enum SubscriptionPaymentMethod: string
{
    case MobileMoney = 'mobile_money';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::MobileMoney => 'Mobile money',
            self::BankTransfer => 'Bank transfer',
            self::Cash => 'Cash',
        };
    }
}
