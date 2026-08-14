<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The shop profile a customer reads before agreeing to pay cash at the door. The phone
 * number and delivery notes matter more here than anywhere else: they are the only way
 * to reach whoever will knock on the door.
 *
 * The slug is not editable — it is the public shop URL and may already be shared.
 */
beforeEach(function (): void {
    Storage::fake('public');

    $this->vendor = Vendor::factory()->sellable()->create();
    $this->vendorUser = $this->vendor->user;
    $this->vendorUser->update(['role' => UserRole::Vendor]);
});

it('shows the shop profile with no artwork yet', function (): void {
    $this->actingAs($this->vendorUser)
        ->get(route('vendor.shop.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vendor/shop')
            ->where('shop.shop_name', $this->vendor->shop_name)
            ->where('shop.slug', $this->vendor->slug)
            ->where('shop.logo_url', null)
            ->where('shop.banner_url', null),
        );
});

it('shows the artwork once it has been uploaded', function (): void {
    $this->vendor->addMedia(UploadedFile::fake()->image('logo.jpg'))->toMediaCollection('logo');
    $this->vendor->addMedia(UploadedFile::fake()->image('banner.jpg'))->toMediaCollection('banner');

    $this->actingAs($this->vendorUser)
        ->get(route('vendor.shop.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('shop.logo_url', fn (?string $url): bool => $url !== null)
            ->where('shop.banner_url', fn (?string $url): bool => $url !== null),
        );
});

it('updates the profile without touching the public shop url', function (): void {
    $slug = $this->vendor->slug;

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.shop.update'), [
            'shop_name' => 'Ikawa House',
            'description' => 'Coffee from Huye.',
            'phone' => '+250788111222',
            'email' => 'hello@ikawa.test',
            'delivery_notes' => 'We deliver on motorbike before 6pm.',
        ])
        ->assertRedirect(route('vendor.shop.edit'));

    $this->vendor->refresh();

    expect($this->vendor->shop_name)->toBe('Ikawa House')
        ->and($this->vendor->phone)->toBe('+250788111222')
        ->and($this->vendor->delivery_notes)->toBe('We deliver on motorbike before 6pm.')
        ->and($this->vendor->slug)->toBe($slug);
});

it('stores blank optional profile fields as null', function (): void {
    $this->actingAs($this->vendorUser)
        ->post(route('vendor.shop.update'), [
            'shop_name' => 'Minimal Shop',
            'description' => '',
            'phone' => '+250788111222',
            'email' => '',
            'delivery_notes' => '',
        ])
        ->assertRedirect();

    $this->vendor->refresh();

    expect($this->vendor->description)->toBeNull()
        ->and($this->vendor->email)->toBeNull()
        ->and($this->vendor->delivery_notes)->toBeNull();
});

it('replaces the logo and banner when new artwork is uploaded', function (): void {
    $this->vendor->addMedia(UploadedFile::fake()->image('old-logo.jpg'))->toMediaCollection('logo');

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.shop.update'), [
            'shop_name' => $this->vendor->shop_name,
            'phone' => $this->vendor->phone,
            'logo' => UploadedFile::fake()->image('new-logo.jpg'),
            'banner' => UploadedFile::fake()->image('new-banner.jpg'),
        ])
        ->assertRedirect();

    $this->vendor->refresh();

    expect($this->vendor->getMedia('logo'))->toHaveCount(1)
        ->and($this->vendor->getFirstMedia('logo')->file_name)->toBe('new-logo.jpg')
        ->and($this->vendor->getMedia('banner'))->toHaveCount(1);
});

it('clears the artwork when the vendor asks for it to be removed', function (): void {
    $this->vendor->addMedia(UploadedFile::fake()->image('logo.jpg'))->toMediaCollection('logo');
    $this->vendor->addMedia(UploadedFile::fake()->image('banner.jpg'))->toMediaCollection('banner');

    $this->actingAs($this->vendorUser)
        ->post(route('vendor.shop.update'), [
            'shop_name' => $this->vendor->shop_name,
            'phone' => $this->vendor->phone,
            'remove_logo' => true,
            'remove_banner' => true,
        ])
        ->assertRedirect();

    $this->vendor->refresh();

    expect($this->vendor->getMedia('logo'))->toHaveCount(0)
        ->and($this->vendor->getMedia('banner'))->toHaveCount(0);
});

it('refuses a profile with no shop name', function (): void {
    $this->actingAs($this->vendorUser)
        ->post(route('vendor.shop.update'), ['phone' => '+250788111222'])
        ->assertSessionHasErrors('shop_name');
});

it('refuses an email that is not an email', function (): void {
    $this->actingAs($this->vendorUser)
        ->post(route('vendor.shop.update'), [
            'shop_name' => $this->vendor->shop_name,
            'phone' => $this->vendor->phone,
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors('email');
});

/**
 * The shop is already hidden from the storefront, and keeping the profile accurate is
 * part of getting back onto it.
 */
it('stays editable for a vendor whose subscription has lapsed', function (): void {
    $expired = Vendor::factory()->expired()->create();
    $expired->user->update(['role' => UserRole::Vendor]);

    $this->actingAs($expired->user)
        ->post(route('vendor.shop.update'), [
            'shop_name' => 'Back Soon',
            'phone' => $expired->phone,
        ])
        ->assertRedirect();

    expect($expired->fresh()->shop_name)->toBe('Back Soon');
});
