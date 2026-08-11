<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuid;
use Carbon\CarbonImmutable;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cart belongs to a user once they sign in, and to a session before that, so
 * user_id and session_id are both nullable.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property int|null $user_id
 * @property string|null $session_id
 * @property string $currency
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, CartItem> $items
 */
final class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    use HasUuid;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }
}
