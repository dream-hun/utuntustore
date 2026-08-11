<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\CouponUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable redemption record.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property int $coupon_id
 * @property int $user_id
 * @property int $order_id
 * @property int $discount_amount
 * @property-read CarbonImmutable|null $created_at
 * @property-read Coupon $coupon
 * @property-read User $user
 * @property-read Order $order
 */
final class CouponUsage extends Model
{
    /** @use HasFactory<CouponUsageFactory> */
    use HasFactory;

    use HasUuid;

    /**
     * A redemption is written once and never changed, so the table has no updated_at.
     */
    public const UPDATED_AT = null;

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
