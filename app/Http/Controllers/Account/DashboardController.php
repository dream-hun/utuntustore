<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Account\FindReviewableOrderItems;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\VendorOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's account overview.
 *
 * Everything here is a pointer into a fuller screen, so the counts are deferred: the
 * page shell renders immediately and the numbers arrive after, which matters on the
 * slow connections most of this marketplace's customers are on.
 */
final class DashboardController extends Controller
{
    public function __construct(private readonly FindReviewableOrderItems $findReviewableOrderItems) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->currentUser($request);

        return Inertia::render('account/index', [
            'recentOrders' => Inertia::defer(fn (): array => $user->orders()
                ->with(['vendorOrders.vendor'])
                ->latest('placed_at')
                ->limit(3)
                ->get()
                ->map(fn (Order $order): array => [
                    'id' => $order->uuid,
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'total' => $order->total,
                    'currency' => $order->currency,
                    'placed_at' => $order->placed_at,
                    'shops' => $order->vendorOrders
                        ->map(static fn (VendorOrder $vendorOrder): string => $vendorOrder->vendor->shop_name)
                        ->all(),
                ])
                ->all()),

            'defaultAddress' => Inertia::defer(fn (): ?array => $this->defaultAddress($user->id)),

            'wishlistCount' => Inertia::defer(fn (): int => $user->wishlist === null
                ? 0
                : $user->wishlist->items()->count()),

            'awaitingReview' => Inertia::defer(fn (): array => $this->awaitingReview($user)),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function defaultAddress(int $userId): ?array
    {
        $address = Address::query()
            ->with(['district', 'sector'])
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->first();

        if (! $address instanceof Address) {
            return null;
        }

        return [
            'id' => $address->uuid,
            'recipient' => $address->first_name.' '.$address->last_name,
            'phone' => $address->phone,
            'district' => $address->district->name,
            'sector' => $address->sector->name,
            'landmark' => $address->landmark,
        ];
    }

    /**
     * Delivered purchases the customer has not written about yet.
     *
     * @return array{count: int, items: array<int, array<string, mixed>>}
     */
    private function awaitingReview(User $user): array
    {
        $query = $this->findReviewableOrderItems->handle($user)->with(['vendorOrder']);

        return [
            'count' => (clone $query)->count(),
            'items' => $query
                ->latest('id')
                ->limit(3)
                ->get()
                ->map(fn (OrderItem $item): array => [
                    'id' => $item->uuid,
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'delivered_at' => $item->vendorOrder->delivered_at,
                ])
                ->all(),
        ];
    }
}
