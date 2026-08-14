<?php

declare(strict_types=1);

use App\Enums\CouponType;
use App\Enums\OrderPaymentMethod;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\SubscriptionPaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Enums\VendorSubscriptionStatus;

/**
 * Every enum label is user-facing text, so a missing arm is a screen showing a raw
 * database value. Driving each case through its own match keeps that from shipping.
 */
it('labels every coupon type', function (CouponType $type, string $label): void {
    expect($type->label())->toBe($label);
})->with([
    [CouponType::Percentage, 'Percentage off'],
    [CouponType::Fixed, 'Fixed amount off'],
]);

it('labels the only payment method the marketplace accepts', function (): void {
    expect(OrderPaymentMethod::CashOnDelivery->label())->toBe('Cash on delivery');
});

it('labels every order status', function (OrderStatus $status, string $label): void {
    expect($status->label())->toBe($label);
})->with([
    [OrderStatus::Pending, 'Pending'],
    [OrderStatus::Confirmed, 'Confirmed'],
    [OrderStatus::Processing, 'Processing'],
    [OrderStatus::Shipped, 'Shipped'],
    [OrderStatus::Delivered, 'Delivered'],
    [OrderStatus::Cancelled, 'Cancelled'],
]);

/**
 * The lifecycle ends at Delivered: there is no settlement or payout stage, because
 * the vendor collected the cash at the door and the platform holds nothing.
 */
it('allows only the legal next steps through the lifecycle', function (): void {
    expect(OrderStatus::Pending->allowedTransitions())->toBe([OrderStatus::Confirmed, OrderStatus::Cancelled])
        ->and(OrderStatus::Confirmed->allowedTransitions())->toBe([OrderStatus::Processing, OrderStatus::Cancelled])
        ->and(OrderStatus::Processing->allowedTransitions())->toBe([OrderStatus::Shipped, OrderStatus::Cancelled])
        ->and(OrderStatus::Shipped->allowedTransitions())->toBe([OrderStatus::Delivered])
        ->and(OrderStatus::Delivered->allowedTransitions())->toBe([])
        ->and(OrderStatus::Cancelled->allowedTransitions())->toBe([]);
});

it('refuses a jump that skips the road', function (): void {
    expect(OrderStatus::Pending->canTransitionTo(OrderStatus::Confirmed))->toBeTrue()
        ->and(OrderStatus::Pending->canTransitionTo(OrderStatus::Delivered))->toBeFalse()
        // Once a vendor is on the road, cancelling is a conversation rather than a button.
        ->and(OrderStatus::Shipped->canTransitionTo(OrderStatus::Cancelled))->toBeFalse();
});

it('knows which statuses can no longer change', function (OrderStatus $status, bool $isFinal): void {
    expect($status->isFinal())->toBe($isFinal);
})->with([
    [OrderStatus::Pending, false],
    [OrderStatus::Confirmed, false],
    [OrderStatus::Processing, false],
    [OrderStatus::Shipped, false],
    [OrderStatus::Delivered, true],
    [OrderStatus::Cancelled, true],
]);

/**
 * Only cancellation hands stock back to the catalog. Delivered stock is gone because
 * the goods physically left the shop.
 */
it('releases committed stock only on cancellation', function (): void {
    expect(OrderStatus::Cancelled->releasesStock())->toBeTrue()
        ->and(OrderStatus::Delivered->releasesStock())->toBeFalse()
        ->and(OrderStatus::Pending->releasesStock())->toBeFalse();
});

it('labels every product status', function (ProductStatus $status, string $label): void {
    expect($status->label())->toBe($label);
})->with([
    [ProductStatus::Draft, 'Draft'],
    [ProductStatus::Published, 'Published'],
    [ProductStatus::Archived, 'Archived'],
]);

it('labels every way a subscription can be paid off-platform', function (SubscriptionPaymentMethod $method, string $label): void {
    expect($method->label())->toBe($label);
})->with([
    [SubscriptionPaymentMethod::MobileMoney, 'Mobile money'],
    [SubscriptionPaymentMethod::BankTransfer, 'Bank transfer'],
    [SubscriptionPaymentMethod::Cash, 'Cash'],
]);

it('labels every selling eligibility state', function (SubscriptionStatus $status, string $label): void {
    expect($status->label())->toBe($label);
})->with([
    [SubscriptionStatus::None, 'No subscription'],
    [SubscriptionStatus::Active, 'Active'],
    [SubscriptionStatus::Grace, 'In grace period'],
    [SubscriptionStatus::Expired, 'Expired'],
]);

/**
 * Grace still sells: a vendor who is a day late has not stopped being a shop.
 */
it('permits selling in active and grace only', function (SubscriptionStatus $status, bool $permits): void {
    expect($status->permitsSelling())->toBe($permits);
})->with([
    [SubscriptionStatus::None, false],
    [SubscriptionStatus::Active, true],
    [SubscriptionStatus::Grace, true],
    [SubscriptionStatus::Expired, false],
]);

it('labels every user role', function (UserRole $role, string $label): void {
    expect($role->label())->toBe($label);
})->with([
    [UserRole::Customer, 'Customer'],
    [UserRole::Vendor, 'Vendor'],
    [UserRole::Admin, 'Admin'],
]);

it('labels every vendor application state', function (VendorStatus $status, string $label): void {
    expect($status->label())->toBe($label);
})->with([
    [VendorStatus::Pending, 'Pending review'],
    [VendorStatus::Approved, 'Approved'],
    [VendorStatus::Rejected, 'Rejected'],
    [VendorStatus::Suspended, 'Suspended'],
]);

it('labels every state a subscription row can be in', function (VendorSubscriptionStatus $status, string $label): void {
    expect($status->label())->toBe($label);
})->with([
    [VendorSubscriptionStatus::Pending, 'Pending payment'],
    [VendorSubscriptionStatus::Active, 'Active'],
    [VendorSubscriptionStatus::Expired, 'Expired'],
    [VendorSubscriptionStatus::Cancelled, 'Cancelled'],
]);
