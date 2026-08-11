<?php

declare(strict_types=1);

namespace App\Http\Requests\Vendor;

use App\Enums\OrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Advancing a vendor order through the cash-on-delivery lifecycle.
 *
 * This only checks that the value is a real status. Whether the move is legal from
 * the order's current status is decided by OrderStatus::canTransitionTo() inside
 * TransitionVendorOrder, so an illegal jump such as pending → delivered is rejected
 * for every caller and not just for this form.
 */
final class TransitionVendorOrderRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ];
    }

    public function status(): OrderStatus
    {
        return OrderStatus::from($this->string('status')->toString());
    }
}
