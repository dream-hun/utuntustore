<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The MVP settles every order in cash at the door.
 *
 * This enum exists so a future payment method can be added without a schema change.
 * Adding one is a business-model decision: it would make the platform a money handler.
 */
enum OrderPaymentMethod: string
{
    case CashOnDelivery = 'cash_on_delivery';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Cash on delivery',
        };
    }
}
