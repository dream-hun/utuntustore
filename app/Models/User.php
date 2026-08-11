<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property-read string $uuid
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property UserStatus $status
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Vendor|null $vendor
 * @property-read Collection<int, Address> $addresses
 * @property-read Cart|null $cart
 * @property-read Collection<int, Order> $orders
 * @property-read Collection<int, Review> $reviews
 * @property-read Wishlist|null $wishlist
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
/**
 * The MustVerifyEmail contract is what actually arms email verification. The base
 * Authenticatable already supplies the trait, so `hasVerifiedEmail()` reads correctly
 * either way — but the `verified` middleware and Fortify's registration listener both
 * gate on `instanceof MustVerifyEmail`, so without the contract every protected route
 * lets unverified accounts straight through and no verification mail is ever sent.
 */
final class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasUuid;
    use Notifiable;
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    /** @return HasOne<Vendor, $this> */
    public function vendor(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }

    /** @return HasMany<Address, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /** @return HasOne<Cart, $this> */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasOne<Wishlist, $this> */
    public function wishlist(): HasOne
    {
        return $this->hasOne(Wishlist::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isVendor(): bool
    {
        return $this->role === UserRole::Vendor;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }
}
