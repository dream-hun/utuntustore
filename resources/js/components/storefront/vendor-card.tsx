import { Link } from '@inertiajs/react';
import { Store } from 'lucide-react';
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

            <div className="relative px-4 pb-5">
                {/* Logo lifted out of the banner */}
                <span className="absolute -top-7 grid size-14 place-items-center overflow-hidden rounded-2xl border-4 border-card bg-cream">
                    {vendor.logo_url ? (
                        <img
                            src={vendor.logo_url}
                            alt=""
                            loading="lazy"
                            className="size-full object-cover"
                        />
                    ) : (
                        <Store
                            className="size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                    )}
                </span>

                <div className="mt-9 space-y-1.5">
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
