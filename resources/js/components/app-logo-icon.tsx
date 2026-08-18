import type { SVGAttributes } from 'react';

/**
 * The agaseke — the lidded, chevron-woven Rwandan peace basket.
 *
 * Monochrome and drawn with `fill-current` on purpose: the mark sits on the teal
 * storefront header, on a neutral admin sidebar and on both auth backgrounds, so
 * every caller sets its own colour. The favicon carries the teal tile instead.
 *
 * The weave is two broad chevrons per band rather than a fine one. Anything finer
 * disappears by 24px, and this renders at 16px in a browser tab.
 *
 * The viewBox is cropped to the basket rather than to the favicon's 48-square, so
 * the mark fills the callers' tiles instead of floating in its own padding.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="8 2 32 42" xmlns="http://www.w3.org/2000/svg">
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M24 3.5L39 20L9 20ZM10.5 20.5L37.5 20.5L32 42L16 42ZM12.5 16.5L18.25 12L24 16.5L29.75 12L35.5 16.5L35.5 19.3L29.75 14.8L24 19.3L18.25 14.8L12.5 19.3ZM15 33L19.5 28L24 33L28.5 28L33 33L33 35.8L28.5 30.8L24 35.8L19.5 30.8L15 35.8Z"
            />
        </svg>
    );
}
