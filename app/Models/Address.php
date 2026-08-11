<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AddressFactory;
use App\Concerns\HasUuid;
use App\Enums\AddressType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Rwandan delivery address. District and sector are required because they are what
 * delivery-coverage matching runs on; the landmark is what usually gets a courier
 * to the door.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property int $user_id
 * @property AddressType $type
 * @property string $first_name
 * @property string $last_name
 * @property string $phone
 * @property string $country
 * @property int $district_id
 * @property int $sector_id
 * @property string|null $cell
 * @property string|null $village
 * @property string|null $address_line
 * @property string|null $landmark
 * @property bool $is_default
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read District $district
 * @property-read Sector $sector
 */
final class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    use HasUuid;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<District, $this> */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
            'is_default' => 'boolean',
        ];
    }
}
