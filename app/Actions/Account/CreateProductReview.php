<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\ReviewStatus;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;

/**
 * Records a customer's review of something they actually received.
 *
 * Eligibility (the item is theirs, its vendor order was delivered, it has not been
 * reviewed) is ReviewPolicy::createForOrderItem and is checked before this runs.
 * Every new review starts pending: nothing a customer writes reaches the storefront
 * without a moderator approving it.
 */
final readonly class CreateProductReview
{
    /**
     * @param  array{rating: int, title: string|null, comment: string|null}  $attributes
     */
    public function handle(User $user, OrderItem $orderItem, array $attributes): Review
    {
        $review = new Review;
        $review->user_id = $user->id;
        $review->product_id = (int) $orderItem->product_id;
        $review->order_item_id = $orderItem->id;
        $review->rating = $attributes['rating'];
        $review->title = $attributes['title'];
        $review->comment = $attributes['comment'];
        $review->status = ReviewStatus::Pending;
        $review->save();

        return $review;
    }
}
