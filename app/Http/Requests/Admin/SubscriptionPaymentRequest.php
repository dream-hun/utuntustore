<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SubscriptionPaymentMethod;
use App\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

/**
 * Validates an off-platform payment an admin has confirmed receiving.
 *
 * The reference is required, not optional: a subscription may never go active without
 * one (BR-12). It is the only trace the platform has that 20,000 RWF actually changed
 * hands, since no payment ever passes through the system.
 */
final class SubscriptionPaymentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor' => ['required', 'uuid', Rule::exists('vendors', 'uuid')],
            'payment_method' => ['required', Rule::enum(SubscriptionPaymentMethod::class)],
            'reference' => ['required', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference.required' => __('Record the mobile money, bank or receipt reference — a subscription cannot go active without one.'),
            'paid_at.before_or_equal' => __('A payment cannot be recorded for a future date.'),
        ];
    }

    public function vendor(): Vendor
    {
        return Vendor::query()
            ->where('uuid', $this->string('vendor')->toString())
            ->firstOrFail();
    }

    public function paymentMethod(): SubscriptionPaymentMethod
    {
        return SubscriptionPaymentMethod::from($this->string('payment_method')->toString());
    }

    /**
     * Null lets the action fall back to now().
     */
    public function paidAt(): ?CarbonImmutable
    {
        $paidAt = $this->date('paid_at');

        return $paidAt === null ? null : Date::instance($paidAt);
    }
}
