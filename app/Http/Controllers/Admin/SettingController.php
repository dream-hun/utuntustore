<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\UpdatePlatformSettings;
use App\Enums\VendorSubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlatformSettingsRequest;
use App\Models\VendorSubscription;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The subscription terms: fee, currency, period length and grace period.
 *
 * These are read when a payment is recorded and copied onto that subscription row, so
 * changing them here is forward-looking only — no historical row is ever rewritten.
 */
final class SettingController extends Controller
{
    public function edit(Settings $settings): Response
    {
        return Inertia::render('admin/settings', [
            'settings' => [
                'vendor_subscription_fee' => $settings->subscriptionFee(),
                'vendor_subscription_currency' => $settings->subscriptionCurrency(),
                'vendor_subscription_days' => $settings->subscriptionDays(),
                'vendor_subscription_grace_days' => $settings->subscriptionGraceDays(),
            ],

            // Shown next to the fee field so it is obvious what a change does and does
            // not touch.
            'liveSubscriptions' => VendorSubscription::query()
                ->where('status', VendorSubscriptionStatus::Active)
                ->count(),
        ]);
    }

    public function update(PlatformSettingsRequest $request, UpdatePlatformSettings $update): RedirectResponse
    {
        $update->handle($request->settings());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Platform settings saved. Existing subscriptions keep the fee they were charged.'),
        ]);

        return back();
    }
}
