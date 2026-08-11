<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\AddressType;
use App\Models\Address;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates one of a customer's delivery addresses.
 *
 * The district and sector arrive already resolved to ids by the form request, which
 * derives the district from the chosen sector so the pair can never disagree — a
 * mismatched pair would silently break vendor delivery-coverage matching.
 */
final readonly class SaveAddress
{
    public function __construct(private SetDefaultAddress $setDefaultAddress) {}

    /**
     * @param  array{type: string, first_name: string, last_name: string, phone: string, district_id: int, sector_id: int, cell: string|null, village: string|null, address_line: string|null, landmark: string|null, is_default: bool}  $attributes
     */
    public function handle(User $user, array $attributes, ?Address $address = null): Address
    {
        return DB::transaction(function () use ($user, $attributes, $address): Address {
            $address ??= new Address;

            $address->user_id = $user->id;
            $address->type = AddressType::from($attributes['type']);
            $address->first_name = $attributes['first_name'];
            $address->last_name = $attributes['last_name'];
            $address->phone = $attributes['phone'];
            $address->country = 'RW';
            $address->district_id = $attributes['district_id'];
            $address->sector_id = $attributes['sector_id'];
            $address->cell = $attributes['cell'];
            $address->village = $attributes['village'];
            $address->address_line = $attributes['address_line'];
            $address->landmark = $attributes['landmark'];

            // The very first address a customer saves becomes their default, because a
            // customer with no default has nothing for checkout to pre-select.
            $isFirst = ! $user->addresses()->whereKeyNot($address->getKey() ?? 0)->exists();
            $shouldBeDefault = $attributes['is_default'] || $isFirst;

            // A default is only ever raised here, never lowered: a customer cannot
            // clear their default, only promote a different address over it.
            $address->is_default = $address->exists && $address->is_default;
            $address->save();

            if ($shouldBeDefault) {
                $this->setDefaultAddress->handle($address);
            }

            return $address;
        });
    }
}
