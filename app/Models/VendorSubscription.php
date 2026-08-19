<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\SubscriptionPaymentMethod;
use App\Enums\VendorSubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\VendorSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * The platform's revenue ledger: one row per subscription period a vendor paid for.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property int $vendor_id
 * @property int $amount
 * @property string $currency
 * @property VendorSubscriptionStatus $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property SubscriptionPaymentMethod $payment_method
 * @property string|null $reference
 * @property CarbonImmutable|null $paid_at
 * @property int|null $recorded_by
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read Vendor $vendor
 * @property-read User|null $recordedBy
 */
final class VendorSubscription extends Model
{
    /** @use HasFactory<VendorSubscriptionFactory> */
    use HasFactory;

    use HasUuid;

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * The admin who recorded this off-platform payment.
     *
     * Nullable because the admin account may later be deleted without erasing revenue history.
     *
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => VendorSubscriptionStatus::class,
            'payment_method' => SubscriptionPaymentMethod::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
