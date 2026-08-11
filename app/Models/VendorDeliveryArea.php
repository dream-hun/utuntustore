<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\VendorDeliveryAreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a vendor delivers and what they charge for it.
 *
 * A null sector_id means the whole district; a set sector_id overrides the
 * district-wide row for that one sector.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property int $vendor_id
 * @property int $district_id
 * @property int|null $sector_id
 * @property int $delivery_fee
 * @property int $estimated_days_min
 * @property int $estimated_days_max
 * @property bool $is_active
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read Vendor $vendor
 * @property-read District $district
 * @property-read Sector|null $sector
 */
final class VendorDeliveryArea extends Model
{
    /** @use HasFactory<VendorDeliveryAreaFactory> */
    use HasFactory;

    use HasUuid;

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
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
            'is_active' => 'boolean',
        ];
    }
}
