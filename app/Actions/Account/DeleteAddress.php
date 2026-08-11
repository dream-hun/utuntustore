<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\Address;
use App\Models\Order;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Removes one of a customer's delivery addresses.
 *
 * An address that an order was delivered to is part of that order's history and the
 * foreign key is restricted on delete, so it is refused rather than left to blow up
 * as a database error.
 */
final readonly class DeleteAddress
{
    public function __construct(private SetDefaultAddress $setDefaultAddress) {}

    public function handle(Address $address): void
    {
        if ($this->isUsedByAnOrder($address)) {
            throw new DomainException(__('This address is attached to an order, so it cannot be deleted.'));
        }

        DB::transaction(function () use ($address): void {
            $wasDefault = $address->is_default;
            $userId = $address->user_id;

            $address->delete();

            if (! $wasDefault) {
                return;
            }

            // Keep exactly one default alive: promote the newest survivor, so the
            // customer never lands on checkout with nothing pre-selected.
            $successor = Address::query()
                ->where('user_id', $userId)
                ->latest('id')
                ->first();

            if ($successor instanceof Address) {
                $this->setDefaultAddress->handle($successor);
            }
        });
    }

    private function isUsedByAnOrder(Address $address): bool
    {
        return Order::query()
            ->where('shipping_address_id', $address->getKey())
            ->orWhere('billing_address_id', $address->getKey())
            ->exists();
    }
}
