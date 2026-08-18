import { ImageOff } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';

interface ImageProps {
    /** A missing URL and a URL that fails to load render the same placeholder. */
    src: string | null | undefined;
    /** Describes the image itself. Empty marks it decorative. */
    alt: string;
    className?: string;
    /**
     * What a missing or dead image leaves behind. `none` suits decoration, where a
     * placeholder would only draw the eye to something that was never load-bearing.
     */
    fallback?: 'icon' | 'none';
    iconClassName?: string;
    /** Swaps the placeholder glyph where a subject-specific one reads better. */
    icon?: LucideIcon;
    /**
     * Above the fold. Loads eagerly and asks the browser to prioritise the fetch —
     * only ever true for the one image that is the page's LCP element.
     */
    priority?: boolean;
}

/**
 * An <img> that degrades to a placeholder instead of a broken-image glyph.
 *
 * Every image on this site comes from media library, and a conversion that is still
 * queued, has failed, or whose file has been pruned leaves a URL that resolves to
 * nothing. The browser's default for that is the torn-page icon plus the alt text
 * spilling out of the container, which is far uglier inside a rounded product card
 * than an empty frame is. Callers already handled a null URL; the dead-URL case is
 * the one that was missing, and it looks identical here.
 *
 * The failure is tracked per URL rather than as a bare boolean so that swapping the
 * source — the product gallery does, on every thumbnail click — retries the new one
 * instead of inheriting the previous image's failure.
 *
 * The placeholder is always hidden from assistive tech. Labelling it with the alt
 * text would announce an image that demonstrably is not there, and every caller
 * already sits next to the name it would have repeated.
 */
export function Image({
    src,
    alt,
    className,
    fallback = 'icon',
    iconClassName = 'size-6',
    icon: Icon = ImageOff,
    priority = false,
}: ImageProps) {
    const [failedSrc, setFailedSrc] = useState<string | null>(null);

    if (!src || failedSrc === src) {
        if (fallback === 'none') {
            return null;
        }

        return (
            <span
                aria-hidden="true"
                className="flex size-full items-center justify-center text-muted-foreground"
            >
                <Icon className={iconClassName} aria-hidden="true" />
            </span>
        );
    }

    return (
        <img
            src={src}
            alt={alt}
            loading={priority ? 'eager' : 'lazy'}
            decoding={priority ? 'sync' : 'async'}
            fetchPriority={priority ? 'high' : undefined}
            onError={() => setFailedSrc(src)}
            className={className}
        />
    );
}
