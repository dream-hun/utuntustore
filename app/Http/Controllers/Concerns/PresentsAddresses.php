<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Address;

/**
 * Every shape a Rwandan delivery address is serialized into, in one file.
 *
 * There are deliberately three of them, because three screens genuinely need
 * different things from the same row:
 *
 *  - {@see self::addressProps()}  picking one at checkout: enough to tell two
 *                                 saved addresses apart, no administrative codes.
 *  - {@see self::addressDetail()} the address book: the full record, including the
 *                                 province the district sits in.
 *  - {@see self::deliveryLabel()} printed on an order: one recipient line and the
 *                                 directions a vendor needs to find the door.
 *
 * Keeping them together is the point. They were previously spread across five
 * controllers, and the difference between them was invisible from any one of them.
 */
trait PresentsAddresses
{
    /**
     * Requires the address's district and sector relations to be loaded.
     *
     * @return array<string, mixed>
     */
    protected function addressProps(Address $address): array
    {
        return [
            'id' => $address->uuid,
            'first_name' => $address->first_name,
            'last_name' => $address->last_name,
            'phone' => $address->phone,
            'district' => ['id' => $address->district->uuid, 'name' => $address->district->name],
            'sector' => ['id' => $address->sector->uuid, 'name' => $address->sector->name],
            'cell' => $address->cell,
            'village' => $address->village,
            'address_line' => $address->address_line,
            'landmark' => $address->landmark,
            'is_default' => $address->is_default,
        ];
    }

    /**
     * Requires district.province and sector to be loaded.
     *
     * @return array<string, mixed>
     */
    protected function addressDetail(Address $address): array
    {
        return [
            ...$this->addressProps($address),
            'type' => $address->type->value,
            'country' => $address->country,
            'district' => [
                'id' => $address->district->uuid,
                'name' => $address->district->name,
                'code' => $address->district->code,
                'province' => [
                    'id' => $address->district->province->uuid,
                    'name' => $address->district->province->name,
                    'code' => $address->district->province->code,
                ],
            ],
            'sector' => [
                'id' => $address->sector->uuid,
                'name' => $address->sector->name,
                'code' => $address->sector->code,
            ],
        ];
    }

    /**
     * The address as it appears on an order: who to hand the goods to and how to
     * find them. No uuids, because nothing here is selectable.
     *
     * Requires the address's district and sector relations to be loaded.
     *
     * @return array<string, string|null>
     */
    protected function deliveryLabel(Address $address): array
    {
        return [
            'recipient' => $address->first_name.' '.$address->last_name,
            'phone' => $address->phone,
            'district' => $address->district->name,
            'sector' => $address->sector->name,
            'cell' => $address->cell,
            'village' => $address->village,
            'address_line' => $address->address_line,
            // Usually the only part of a Rwandan address that actually finds the door.
            'landmark' => $address->landmark,
        ];
    }
}
