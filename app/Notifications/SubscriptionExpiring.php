<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Vendor;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Warns a vendor that their selling rights are about to lapse.
 *
 * Payment happens off-platform, so this notification cannot take money — its only
 * job is to get the vendor to pay before their shop disappears from the storefront.
 * That makes the deadline and the amount the two things it must state plainly.
 */
final class SubscriptionExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Vendor $vendor,
        private readonly int $daysRemaining,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fee = resolve(Settings::class)->subscriptionFee();
        $graceDays = resolve(Settings::class)->subscriptionGraceDays();

        return (new MailMessage)
            ->subject($this->subject())
            ->greeting(__('Hello :name,', ['name' => $this->vendor->shop_name]))
            ->line($this->body())
            ->line(__('Renewal is :amount for another year.', [
                'amount' => Money::format($fee),
            ]))
            ->line(__('After it expires you have :days days of grace before your shop is hidden from the storefront.', [
                'days' => $graceDays,
            ]))
            // Expiry never deletes anything, and saying so up front prevents a panicked
            // vendor assuming their catalog is gone.
            ->line(__('Your products and order history are never deleted. Paying again restores your shop exactly as it was.'))
            ->action(__('View your subscription'), route('vendor.subscription'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_expiring',
            'vendor_id' => $this->vendor->uuid,
            'days_remaining' => $this->daysRemaining,
            'ends_at' => $this->vendor->subscription_ends_at,
            'title' => $this->subject(),
            'message' => $this->body(),
        ];
    }

    private function subject(): string
    {
        return $this->daysRemaining <= 1
            ? __('Your shop subscription expires tomorrow')
            : __('Your shop subscription expires in :days days', ['days' => $this->daysRemaining]);
    }

    private function body(): string
    {
        return __('Your subscription ends on :date.', [
            'date' => $this->vendor->subscription_ends_at?->toFormattedDateString() ?? '',
        ]);
    }
}
