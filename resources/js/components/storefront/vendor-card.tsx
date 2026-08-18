import { Link } from '@inertiajs/react';
import { Store } from 'lucide-react';
import { Image } from '@/components/image';
import { cn } from '@/lib/utils';
import { show as vendorShow } from '@/routes/vendors';

interface VendorCardProps {
    vendor: {
        id: string;
        shop_name: string;
        slug: string;
        logo_url: string | null;
        description: string | null;
        product_count?: number;
    };
    className?: string;
}

/**
 * Card that surfaces a shop's logo, name, and a short description.
 * Used on the home page's "Meet the shops" section and the vendor listing.
 */
export function VendorCard({ vendor, className }: VendorCardProps) {
    return (
        <Link
            href={vendorShow.url({ vendor: vendor.slug })}
            aria-label={`Visit ${vendor.shop_name}`}
            className={cn(
                'group block overflow-hidden rounded-2xl border border-border bg-card transition duration-500 hover:shadow-[0_12px_30px_-12px_oklch(0.2_0.02_250/0.25)] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                className,
            )}
        >
            <div className="h-24 w-full bg-aqua-soft" />

            {/*
             * pt-9 rather than a margin on the text block: a margin there collapses
             * through this padding-less wrapper, dragging the wrapper — and the logo
             * positioned against it — down over the name instead of clearing it.
             */}
            <div className="relative px-4 pt-9 pb-5">
                {/* Logo lifted out of the banner */}
                <span className="absolute -top-7 left-4 grid size-14 place-items-center overflow-hidden rounded-2xl border-4 border-card bg-cream">
                    <Image
                        src={vendor.logo_url}
                        alt=""
                        icon={Store}
                        iconClassName="size-5"
                        className="size-full object-cover"
                    />
                </span>

                <div className="space-y-1.5">
                    <p className="truncate font-heading text-base transition-colors group-hover:text-primary">
                        {vendor.shop_name}
                    </p>

                    {vendor.description ? (
                        <p className="line-clamp-2 text-xs leading-5 text-muted-foreground">
                            {vendor.description}
                        </p>
                    ) : null}

                    {vendor.product_count !== undefined ? (
                        <p className="pt-1 text-[0.7rem] font-semibold tracking-[0.12em] text-primary uppercase">
                            {vendor.product_count.toLocaleString()} product
                            {vendor.product_count === 1 ? '' : 's'}
                        </p>
                    ) : null}
                </div>
            </div>
        </Link>
    );
}
