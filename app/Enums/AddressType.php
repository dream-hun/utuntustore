<?php

declare(strict_types=1);

namespace App\Enums;

enum AddressType: string
{
    case Shipping = 'shipping';
    case Billing = 'billing';
}
