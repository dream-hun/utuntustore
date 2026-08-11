<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CouponFactory;
use App\Concerns\HasUuid;
use App\Enums\CouponType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A null vendor_id means a platform-wide coupon; otherwise the discount applies only
 * to that vendor's items. The platform takes no commission, so every discount is
 * funded entirely by the vendor.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property int|null $vendor_id
 * @property string $code
 * @property CouponType $type
 * @property int $value
 * @property int $minimum_order_amount
 * @property int|null $maximum_discount
 * @property int|null $usage_limit
 * @property int $used_count
 * @property CarbonImmutable|null $starts_at
 * @property CarbonImmutable|null $expires_at
 * @property bool $is_active
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read Vendor|null $vendor
 * @property-read Collection<int, CouponUsage> $usages
 */
final class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    use HasUuid;

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return HasMany<CouponUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Whether this coupon may still be applied to a checkout.
     *
     * A null starts_at means it is live immediately and a null expires_at means it
     * never lapses; a null usage_limit means unlimited redemptions.
     *
     * This answers for a coupon already in memory, which is what pricing a quote
     * needs. {@see self::scopeRedeemable()} is the same rule expressed in SQL, which
     * is what claiming a redemption needs. The two must agree.
     */
    public function isRedeemable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->usage_limit === null || $this->used_count < $this->usage_limit;
    }

    /**
     * The SQL twin of {@see self::isRedeemable()}.
     *
     * Redemption has to be claimed as a condition of the write, not checked before
     * it: two customers spending the last use of a limited coupon at the same moment
     * would both pass an in-memory check and both be granted the discount.
     *
     * @param  Builder<$this>  $query
     */
    protected function scopeRedeemable(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit');
            });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
