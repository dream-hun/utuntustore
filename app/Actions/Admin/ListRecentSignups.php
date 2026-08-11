<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vendor;

/**
 * The newest arrivals on both sides of the marketplace.
 *
 * Pending vendors surface first because a vendor application sitting unreviewed is a
 * shop that cannot pay a subscription yet.
 */
final readonly class ListRecentSignups
{
    /**
     * @return array{
     *     vendors: array<int, array{id: string, shop_name: string, status: string, created_at: string|null}>,
     *     customers: array<int, array{id: string, name: string, email: string, created_at: string|null}>
     * }
     */
    public function handle(int $limit = 5): array
    {
        return [
            'vendors' => Vendor::query()
                ->latest('created_at')
                ->limit($limit)
                ->get()
                ->map(fn (Vendor $vendor): array => [
                    'id' => $vendor->uuid,
                    'shop_name' => $vendor->shop_name,
                    'status' => $vendor->status->value,
                    'created_at' => $vendor->created_at?->toIso8601String(),
                ])
                ->all(),

            'customers' => User::query()
                ->where('role', UserRole::Customer)
                ->latest('created_at')
                ->limit($limit)
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }
}
