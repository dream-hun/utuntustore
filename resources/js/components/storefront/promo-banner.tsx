import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { cn } from '@/lib/utils';

interface PromoBannerProps {
    title: string;
    description?: string;
    ctaLabel?: string;
    ctaHref?: string;
    variant?: 'primary' | 'accent' | 'muted';
    className?: string;
}

const variantStyles = {
    primary:
        'bg-gradient-to-r from-primary to-primary/80 text-primary-foreground',
    accent: 'bg-gradient-to-r from-amber-500 to-orange-500 text-white',
    muted: 'bg-gradient-to-r from-muted to-muted/60 text-foreground border border-border',
};

/**
 * Full-width promotional banner strip.
 * Used between sections on the home page to surface deals or announcements.
 */
export function PromoBanner({
    title,
    description,
    ctaLabel = 'Shop now',
    ctaHref,
    variant = 'primary',
    className,
}: PromoBannerProps) {
    const content = (
        <div
            className={cn(
                'flex flex-col items-start justify-between gap-4 rounded-2xl px-6 py-8 sm:flex-row sm:items-center sm:px-10',
                variantStyles[variant],
                className,
            )}
        >
            <div className="min-w-0 space-y-1">
                <p className="text-xl font-semibold sm:text-2xl">{title}</p>
                {description ? (
                    <p className="text-sm opacity-90">{description}</p>
                ) : null}
            </div>

            {ctaHref ? (
                <Link
                    href={ctaHref}
                    className={cn(
                        'inline-flex shrink-0 items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none',
                        variant === 'muted'
                            ? 'bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-primary'
                            : 'bg-white/20 text-inherit hover:bg-white/30 focus-visible:ring-white',
                    )}
                >
                    {ctaLabel}
                    <ArrowRight className="size-4" aria-hidden="true" />
                </Link>
            ) : null}
        </div>
    );

    return content;
}
