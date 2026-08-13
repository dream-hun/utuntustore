<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ModerateVendor;
use App\Enums\SubscriptionStatus;
use App\Enums\VendorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VendorModerateRequest;
use App\Models\Vendor;
use App\Models\VendorOrder;
use App\Models\VendorSubscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vendor oversight: who is on the platform, who is waiting, and who is selling.
 */
final class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Vendor::class);

        $search = mb_trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $subscriptionStatus = (string) $request->query('subscription_status', '');

        $vendors = Vendor::query()
            ->withCount(['products', 'vendorOrders'])
            ->with('user:id,name,email')
            ->when($search !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->where('shop_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"),
            ))
            ->when(
                VendorStatus::tryFrom($status) instanceof VendorStatus,
                fn (Builder $query): Builder => $query->where('status', $status),
            )
            ->when(
                SubscriptionStatus::tryFrom($subscriptionStatus) instanceof SubscriptionStatus,
                fn (Builder $query): Builder => $query->where('subscription_status', $subscriptionStatus),
            )
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Vendor $vendor): array => $this->row($vendor));

        return Inertia::render('admin/vendors/index', [
            'vendors' => $vendors,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'subscription_status' => $subscriptionStatus,
            ],
        ]);
    }

    public function show(Vendor $vendor): Response
    {
        $this->authorize('viewAny', Vendor::class);

        $vendor->loadCount(['products', 'vendorOrders', 'deliveryAreas']);
        $vendor->load('user:id,name,email,phone');

        return Inertia::render('admin/vendors/show', [
            'vendor' => [
                ...$this->row($vendor),
                'description' => $vendor->description,
                'delivery_notes' => $vendor->delivery_notes,
                'is_platform_owned' => $vendor->is_platform_owned,
                'delivery_areas_count' => $vendor->delivery_areas_count,
                'owner' => [
                    'name' => $vendor->user->name,
                    'email' => $vendor->user->email,
                    'phone' => $vendor->user->phone,
                ],
            ],

            'subscriptions' => $vendor->subscriptions()
                ->with('recordedBy:id,name')
                ->latest('starts_at')
                ->limit(10)
                ->get()
                ->map(fn (VendorSubscription $subscription): array => [
                    'id' => $subscription->uuid,
                    'amount' => $subscription->amount,
                    'currency' => $subscription->currency,
                    'status' => $subscription->status->value,
                    'starts_at' => $subscription->starts_at->toIso8601String(),
                    'ends_at' => $subscription->ends_at->toIso8601String(),
                    'payment_method' => $subscription->payment_method->value,
                    'reference' => $subscription->reference,
                    'paid_at' => $subscription->paid_at?->toIso8601String(),
                    'recorded_by' => $subscription->recordedBy?->name,
                ])
                ->all(),

            'recentOrders' => $vendor->vendorOrders()
                ->with('order:id,uuid,order_number')
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn (VendorOrder $vendorOrder): array => [
                    'id' => $vendorOrder->uuid,
                    'order_number' => $vendorOrder->order_number,
                    'parent_order_number' => $vendorOrder->order->order_number,
                    'parent_order_id' => $vendorOrder->order->uuid,
                    'status' => $vendorOrder->status->value,
                    'total' => $vendorOrder->total,
                    'created_at' => $vendorOrder->created_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    /**
     * Approve, reject or suspend a shop.
     */
    public function moderate(VendorModerateRequest $request, Vendor $vendor, ModerateVendor $moderate): RedirectResponse
    {
        $this->authorize('moderate', Vendor::class);

        $status = $request->status();

        $moderate->handle($vendor, $status);

        Inertia::flash('toast', [
            'type' => $status === VendorStatus::Approved ? 'success' : 'warning',
            'message' => match ($status) {
                VendorStatus::Approved => __(':shop is approved. They can sell once a subscription payment is recorded.', ['shop' => $vendor->shop_name]),
                VendorStatus::Rejected => __(':shop has been rejected.', ['shop' => $vendor->shop_name]),
                default => __(':shop is suspended and hidden from the storefront. No refund is involved — the platform never held the money.', ['shop' => $vendor->shop_name]),
            },
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Vendor $vendor): array
    {
        return [
            'id' => $vendor->uuid,
            'shop_name' => $vendor->shop_name,
            'slug' => $vendor->slug,
            'phone' => $vendor->phone,
            'email' => $vendor->email,
            'status' => $vendor->status->value,
            'subscription_status' => $vendor->subscription_status->value,
            'subscription_ends_at' => $vendor->subscription_ends_at?->toIso8601String(),
            'approved_at' => $vendor->approved_at?->toIso8601String(),
            'can_sell' => $vendor->canSell(),
            'products_count' => $vendor->products_count,
            'orders_count' => $vendor->vendor_orders_count,
            'owner_name' => $vendor->user->name,
            'created_at' => $vendor->created_at?->toIso8601String(),
        ];
    }
}
