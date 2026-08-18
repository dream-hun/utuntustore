import { Link } from '@inertiajs/react';
import { Image } from '@/components/image';
import { cn } from '@/lib/utils';

type PromoVariant = 'primary' | 'ink' | 'cream' | 'aqua';

interface PromoBannerProps {
    title: string;
    /** Small tracked label above the title. */
    eyebrow?: string;
    description?: string;
    ctaLabel?: string;
    ctaHref?: string;
    /** Decorative image tucked into the right of the panel. */
    image?: string;
    variant?: PromoVariant;
    className?: string;
}

const panelStyles: Record<PromoVariant, string> = {
    primary: 'bg-primary text-primary-foreground',
    ink: 'bg-ink text-white',
    cream: 'bg-cream text-foreground',
    aqua: 'bg-aqua-soft text-foreground',
};

/** On dark panels the CTA inverts; on light ones it takes the primary fill. */
const ctaStyles: Record<PromoVariant, string> = {
    primary: 'bg-background text-foreground',
    ink: 'bg-primary text-primary-foreground',
    cream: 'bg-primary text-primary-foreground',
    aqua: 'bg-primary text-primary-foreground',
};

/**
 * The large rounded promo panel the storefront breaks its product grids with.
 * Used between sections on the home page to surface offers or explain how
 * ordering from several shops at once works.
 */
export function PromoBanner({
    title,
    eyebrow,
    description,
    ctaLabel = 'Shop now',
    ctaHref,
    image,
    variant = 'primary',
    className,
}: PromoBannerProps) {
    return (
        <article
            className={cn(
                'relative overflow-hidden rounded-3xl p-7 sm:p-10',
                panelStyles[variant],
                className,
            )}
        >
            <div
                className={cn(
                    'relative z-10 max-w-sm',
                    /* Keep the copy clear of the image, which bleeds in over the right 40%. */
                    image && 'sm:max-w-[55%]',
                )}
            >
                {eyebrow ? (
                    <span className="eyebrow opacity-75">{eyebrow}</span>
                ) : null}

                <h3 className="mt-3 text-2xl leading-tight sm:text-3xl">
                    {title}
                </h3>

                {description ? (
                    <p
                        className={cn(
                            'mt-3 text-sm leading-6',
                            variant === 'cream' || variant === 'aqua'
                                ? 'text-muted-foreground'
                                : 'opacity-80',
                        )}
                    >
                        {description}
                    </p>
                ) : null}

                {ctaHref ? (
                    <Link
                        href={ctaHref}
                        className={cn(
                            'mt-6 inline-flex rounded-full px-5 py-2.5 text-sm font-semibold transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            ctaStyles[variant],
                        )}
                    >
                        {ctaLabel}
                    </Link>
                ) : null}
            </div>

            {image ? (
                <Image
                    src={image}
                    alt=""
                    fallback="none"
                    className="pointer-events-none absolute inset-y-0 right-0 hidden h-full w-2/5 object-cover opacity-70 sm:block"
                />
            ) : null}
        </article>
    );
}
