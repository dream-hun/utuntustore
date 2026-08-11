<?php

declare(strict_types=1);

namespace App\Actions\Storefront;

use App\Enums\AddressType;
use App\Models\Address;
use App\Models\District;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a delivery address from the checkout screen.
 *
 * District and sector are mandatory because they are the only things delivery
 * coverage can be matched on — without them no vendor can be told whether they
 * serve this customer.
 */
final readonly class CreateDeliveryAddress
{
    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     phone: string,
     *     district_id: string,
     *     sector_id: string,
     *     cell?: string|null,
     *     village?: string|null,
     *     address_line?: string|null,
     *     landmark?: string|null,
     *     is_default?: bool,
     * }  $data
     */
    public function handle(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data): Address {
            $district = District::query()->where('uuid', $data['district_id'])->firstOrFail();
            $sector = Sector::query()->where('uuid', $data['sector_id'])->firstOrFail();

            // The first address a customer saves becomes their default, otherwise
            // checkout would open with nothing selected on their very next visit.
            $isDefault = ($data['is_default'] ?? false) || $user->addresses()->doesntExist();

            if ($isDefault) {
                $user->addresses()->update(['is_default' => false]);
            }

            $address = new Address;

            $address->user_id = $user->id;
            $address->type = AddressType::Shipping;
            $address->first_name = $data['first_name'];
            $address->last_name = $data['last_name'];
            $address->phone = $data['phone'];
            $address->country = 'RW';
            $address->district_id = $district->id;
            $address->sector_id = $sector->id;
            $address->cell = $data['cell'] ?? null;
            $address->village = $data['village'] ?? null;
            $address->address_line = $data['address_line'] ?? null;
            $address->landmark = $data['landmark'] ?? null;
            $address->is_default = $isDefault;
            $address->save();

            return $address;
        });
    }
}
