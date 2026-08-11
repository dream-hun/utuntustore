<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\VendorOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * The purchases a customer is entitled to review but has not.
 *
 * This is the query form of ReviewPolicy::createForOrderItem — the item is theirs,
 * its vendor order was reported delivered, and it carries no review yet. The policy
 * stays the authority for a single item; this exists so the account area can list
 * and count them without duplicating the rule in two controllers.
 *
 * Items whose product has since been deleted are excluded: a review has to point at
 * a product row, and there is nothing left to point at.
 */
final readonly class FindReviewableOrderItems
{
    /**
     * @return Builder<OrderItem>
     */
    public function handle(User $user): Builder
    {
        return OrderItem::query()
            ->whereNotNull('product_id')
            ->whereIn('order_id', Order::query()->where('user_id', $user->id)->select('id'))
            ->whereIn('vendor_order_id', VendorOrder::query()->whereNotNull('delivered_at')->select('id'))
            ->whereDoesntHave('review');
    }
}
