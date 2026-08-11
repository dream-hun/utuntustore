<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReviewFactory;
use App\Concerns\HasUuid;
use App\Enums\ReviewStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tied to a specific delivered order item, which is what makes the review verifiable
 * and enforces one review per purchase.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property int $user_id
 * @property int $product_id
 * @property int $order_item_id
 * @property int $rating
 * @property string|null $title
 * @property string|null $comment
 * @property ReviewStatus $status
 * @property-read CarbonImmutable|null $created_at
 * @property-read CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Product $product
 * @property-read OrderItem $orderItem
 */
final class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    use HasUuid;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
        ];
    }
}
