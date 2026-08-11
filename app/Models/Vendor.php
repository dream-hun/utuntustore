<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VendorFactory;
use App\Concerns\HasUuid;
use App\Enums\SubscriptionStatus;
use App\Enums\VendorStatus;
use App\Enums\VendorSubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property int $user_id
 * @property string $shop_name
 * @property string $slug
 * @property string|null $description
 * @property string $phone
 * @property string|null $email
 * @property VendorStatus $status
 * @property bool $is_platform_owned
 * @property SubscriptionStatus $subscription_status
 * @property CarbonImmutable|null $subscription_ends_at
 * @property string|null $delivery_notes
 * @property CarbonImmutable|null $approved_at
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, VendorSubscription> $subscriptions
 * @property-read Collection<int, VendorDeliveryArea> $deliveryAreas
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, VendorOrder> $vendorOrders
 * @property-read Collection<int, Coupon> $coupons
 */
final class Vendor extends Model implements HasMedia
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    use HasUuid;
    use InteractsWithMedia;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<VendorSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(VendorSubscription::class);
    }

    /** @return HasMany<VendorDeliveryArea, $this> */
    public function deliveryAreas(): HasMany
    {
        return $this->hasMany(VendorDeliveryArea::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<VendorOrder, $this> */
    public function vendorOrders(): HasMany
    {
        return $this->hasMany(VendorOrder::class);
    }

    /** @return HasMany<Coupon, $this> */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * Whether this vendor may currently put inventory in front of customers.
     *
     * Ownership and selling eligibility are distinct: an expired vendor still owns
     * their data and keeps access to their dashboard, orders and sales history.
     */
    public function canSell(): bool
    {
        if ($this->status !== VendorStatus::Approved) {
            return false;
        }

        return $this->is_platform_owned || $this->subscription_status->permitsSelling();
    }

    public function currentSubscription(): ?VendorSubscription
    {
        return $this->subscriptions()
            ->where('status', VendorSubscriptionStatus::Active)
            ->latest('ends_at')
            ->first();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
        $this->addMediaCollection('banner')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->width(200)
            ->height(200);

        $this->addMediaConversion('web')
            ->width(1200);
    }

    /**
     * Constrain a query to vendors eligible to sell on the storefront.
     *
     * @param  Builder<$this>  $query
     */
    protected function scopeSellable(Builder $query): void
    {
        $query->where('status', VendorStatus::Approved)
            ->where(function (Builder $query): void {
                $query->whereIn('subscription_status', [SubscriptionStatus::Active, SubscriptionStatus::Grace])
                    ->orWhere('is_platform_owned', true);
            });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VendorStatus::class,
            'subscription_status' => SubscriptionStatus::class,
            'subscription_ends_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_platform_owned' => 'boolean',
        ];
    }
}
