<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\Address;
use Illuminate\Support\Facades\DB;

/**
 * Promotes one address to be the customer's default.
 *
 * Checkout reads the default address to pre-select where an order is delivered, so
 * a customer with two defaults — or none — is a broken checkout. Clearing the old
 * default and setting the new one therefore happens in a single transaction: the
 * database must never be observable in a state with two defaults.
 */
final readonly class SetDefaultAddress
{
    public function handle(Address $address): Address
    {
        return DB::transaction(function () use ($address): Address {
            Address::query()
                ->where('user_id', $address->user_id)
                ->whereKeyNot($address->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $address->is_default = true;
            $address->save();

            return $address;
        });
    }
}
