<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Vendor;
use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Media Library builds a conversion's URL from a naming convention, so it hands back
 * a perfectly-formed link to a file that may not exist. Only `thumb` is nonQueued;
 * every `web` URL is dead until a queue worker has drained PerformConversionsJob.
 *
 * These cover the fallback that keeps that from reaching an <img> tag.
 */
beforeEach(function (): void {
    Storage::fake('public');
});

it('returns null when there is no media at all', function (): void {
    expect(MediaUrl::for(null))->toBeNull()
        ->and(MediaUrl::for(null, 'thumb'))->toBeNull();
});

it('returns null for an empty collection', function (): void {
    $vendor = Vendor::factory()->create();

    expect(MediaUrl::fromCollection($vendor, 'logo', 'thumb'))->toBeNull();
});

it('serves the conversion once it has been generated', function (): void {
    $vendor = Vendor::factory()->create();
    $vendor->addMedia(UploadedFile::fake()->image('logo.jpg'))->toMediaCollection('logo');

    $media = $vendor->refresh()->getFirstMedia('logo');

    expect($media->hasGeneratedConversion('thumb'))->toBeTrue()
        ->and(MediaUrl::for($media, 'thumb'))->toBe($media->getUrl('thumb'))
        ->and(MediaUrl::for($media, 'thumb'))->not->toBe($media->getUrl());
});

it('falls back to the original while a queued conversion is still pending', function (): void {
    Queue::fake();

    $vendor = Vendor::factory()->create();
    $vendor->addMedia(UploadedFile::fake()->image('banner.jpg'))->toMediaCollection('banner');

    $media = $vendor->refresh()->getFirstMedia('banner');

    expect($media->hasGeneratedConversion('web'))->toBeFalse()
        ->and(MediaUrl::for($media, 'web'))->toBe($media->getUrl())
        ->and(MediaUrl::for($media, 'web'))->not->toBe($media->getUrl('web'));
});

it('serves the original when no conversion is asked for', function (): void {
    $product = Product::factory()->create();
    $product->addMedia(UploadedFile::fake()->image('shoe.jpg'))->toMediaCollection('images');

    $media = $product->refresh()->getFirstMedia('images');

    expect(MediaUrl::for($media))->toBe($media->getUrl())
        ->and(MediaUrl::fromCollection($product, 'images'))->toBe($media->getUrl());
});

it('keeps a pending banner conversion off the shop screen', function (): void {
    Queue::fake();

    $vendor = Vendor::factory()->sellable()->create();
    $vendor->addMedia(UploadedFile::fake()->image('banner.jpg'))->toMediaCollection('banner');

    $this->get(route('vendors.show', ['vendor' => $vendor->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('vendor.banner_url', $vendor->refresh()->getFirstMedia('banner')?->getUrl()),
        );
});
