<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\ReviewStatus;
use App\Models\Review;

/**
 * Rewrites a review the customer already left.
 *
 * An edited review drops back to pending. Otherwise an approved one-star review
 * could be rewritten into anything at all and stay published without a moderator
 * ever seeing the new text.
 */
final readonly class UpdateProductReview
{
    /**
     * @param  array{rating: int, title: string|null, comment: string|null}  $attributes
     */
    public function handle(Review $review, array $attributes): Review
    {
        $review->rating = $attributes['rating'];
        $review->title = $attributes['title'];
        $review->comment = $attributes['comment'];
        $review->status = ReviewStatus::Pending;
        $review->save();

        return $review;
    }
}
