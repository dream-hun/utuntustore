<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DistrictFactory;
use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property int $province_id
 * @property string $name
 * @property string $code
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read Province $province
 * @property-read Collection<int, Sector> $sectors
 */
final class District extends Model
{
    /** @use HasFactory<DistrictFactory> */
    use HasFactory;

    use HasUuid;

    /** @return BelongsTo<Province, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** @return HasMany<Sector, $this> */
    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
