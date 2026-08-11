<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Account\CreateProductReview;
use App\Actions\Account\FindReviewableOrderItems;
use App\Actions\Account\UpdateProductReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreReviewRequest;
use App\Http\Requests\Account\UpdateReviewRequest;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's own reviews, and the purchases still waiting for one.
 *
 * A review is only ever attached to a delivered order item, which is what makes the
 * rating on a product page mean something: every star came from someone who received
 * the goods and paid the vendor at the door.
 */
final class ReviewController extends Controller
{
    public function __construct(private readonly FindReviewableOrderItems $findReviewableOrderItems) {}

    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);

        $reviews = $user->reviews()
            ->with(['product', 'orderItem.vendorOrder.vendor'])
            ->latest('id')
            ->paginate(10, pageName: 'page')
            ->withQueryString()
            ->through(fn (Review $review): array => [
                'id' => $review->uuid,
                'rating' => $review->rating,
                'title' => $review->title,
                'comment' => $review->comment,
                'status' => $review->status->value,
                'created_at' => $review->created_at,
                'product_name' => $review->orderItem->product_name,
                'shop_name' => $review->orderItem->vendorOrder->vendor->shop_name,
            ]);

        $awaiting = $this->findReviewableOrderItems->handle($user)
            ->with(['vendorOrder.vendor'])
            ->latest('id')
            ->paginate(6, pageName: 'awaiting')
            ->withQueryString()
            ->through(fn (OrderItem $item): array => [
                'id' => $item->uuid,
                'product_name' => $item->product_name,
                'variant_name' => $item->variant_name,
                'shop_name' => $item->vendorOrder->vendor->shop_name,
                'delivered_at' => $item->vendorOrder->delivered_at,
            ]);

        return Inertia::render('account/reviews/index', [
            'reviews' => $reviews,
            'awaitingReview' => $awaiting,
        ]);
    }

    public function store(StoreReviewRequest $request, CreateProductReview $createProductReview): RedirectResponse
    {
        $orderItem = OrderItem::query()
            ->with(['order', 'vendorOrder'])
            ->where('uuid', $request->string('order_item')->toString())
            ->firstOrFail();

        $this->authorize('createForOrderItem', [Review::class, $orderItem]);

        $createProductReview->handle($this->currentUser($request), $orderItem, $request->reviewAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Thanks — your review will appear once it has been checked.'),
        ]);

        return to_route('account.reviews.index');
    }

    public function update(UpdateReviewRequest $request, Review $review, UpdateProductReview $updateProductReview): RedirectResponse
    {
        $this->authorize('update', $review);

        $updateProductReview->handle($review, $request->reviewAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Review updated. It goes back for checking before it shows again.'),
        ]);

        return to_route('account.reviews.index');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review deleted.')]);

        return to_route('account.reviews.index');
    }
}
