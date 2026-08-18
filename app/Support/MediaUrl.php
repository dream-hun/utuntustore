<?php

declare(strict_types=1);

namespace App\Support;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Resolve a media URL that is safe to hand to an <img> tag.
 *
 * `getUrl('web')` and `getFirstMediaUrl($collection, 'web')` both build the
 * conversion's URL from a naming convention — they never check that the file is
 * actually on disk. Only `thumb` is `nonQueued()`, so every `web` URL points at
 * nothing until a queue worker has drained `PerformConversionsJob`, and a
 * conversion that failed outright stays a dead URL forever.
 *
 * Falling back to the original is the right failure mode here: a shop banner that
 * is briefly served full-size costs bandwidth, where a 404 costs the vendor the
 * artwork they just uploaded.
 */
final class MediaUrl
{
    /**
     * The URL for the first item in a collection, or null when the collection is empty.
     */
    public static function fromCollection(HasMedia $model, string $collection, string $conversion = ''): ?string
    {
        $media = $model->getMedia($collection)->first();

        return self::for($media instanceof Media ? $media : null, $conversion);
    }

    /**
     * The URL for one media item, preferring the conversion when it has been generated.
     */
    public static function for(?Media $media, string $conversion = ''): ?string
    {
        if (! $media instanceof Media) {
            return null;
        }

        $url = $conversion !== '' && $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();

        return $url === '' ? null : $url;
    }
}
