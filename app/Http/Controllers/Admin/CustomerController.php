<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SetUserStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerStatusRequest;
use App\Models\User;
use App\Support\Cast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The people buying on the marketplace.
 *
 * Suspension is the only lever here, and it is account-level: EnsureUserHasRole refuses
 * a suspended user on every route and ends their session. Nothing is deleted — the
 * orders a customer placed are also the vendors' delivery history.
 */
final class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = mb_trim($request->string('search')->toString());
        $status = $request->string('status')->toString();

        $customers = User::query()
            ->where('role', UserRole::Customer)
            ->withCount('orders')
            ->withSum('orders', 'total')
            ->when($search !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query): Builder => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"),
            ))
            ->when(
                UserStatus::tryFrom($status) instanceof UserStatus,
                fn (Builder $query): Builder => $query->where('status', $status),
            )
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status->value,
                'orders_count' => $user->orders_count,
                // Gross merchandise value paid to vendors, never platform income.
                'orders_total' => Cast::int($user->getAttribute('orders_sum_total')),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/customers/index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    public function updateStatus(CustomerStatusRequest $request, User $user, SetUserStatus $setStatus): RedirectResponse
    {
        abort_unless($user->isCustomer(), 404);

        $status = $request->status();

        $setStatus->handle($user, $status);

        Inertia::flash('toast', [
            'type' => $status === UserStatus::Suspended ? 'warning' : 'success',
            'message' => $status === UserStatus::Suspended
                ? __(':name is suspended and will be signed out of the marketplace.', ['name' => $user->name])
                : __(':name can shop again.', ['name' => $user->name]),
        ]);

        return back();
    }
}
