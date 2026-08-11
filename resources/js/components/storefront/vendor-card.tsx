import { Link } from '@inertiajs/react';
import { Store } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
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
 * Card that surfaces a vendor's logo, name, and a short description.
 * Used on the home page's "Featured shops" section and the vendor listing.
 */
export function VendorCard({ vendor, className }: VendorCardProps) {
    return (
        <Card
            className={cn(
                'group overflow-hidden rounded-2xl border-border/70 bg-card py-0 shadow-sm transition duration-300 motion-safe:hover:-translate-y-1 motion-safe:hover:border-primary/20 motion-safe:hover:shadow-xl',
                className,
            )}
        >
            <CardContent className="p-0">
                <Link
                    href={vendorShow.url({ vendor: vendor.slug })}
                    className="block focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                    aria-label={`Visit ${vendor.shop_name}`}
                >
                    {/* Banner strip */}
                    <div className="h-24 w-full overflow-hidden bg-gradient-to-br from-primary/20 via-primary/10 to-muted" />

                    <div className="relative px-4 pb-5">
                        {/* Logo lifted out of the banner */}
                        <div className="absolute -top-7 flex size-14 items-center justify-center overflow-hidden rounded-2xl border-4 border-background bg-background shadow-md">
                            {vendor.logo_url ? (
                                <img
                                    src={vendor.logo_url}
                                    alt=""
                                    className="size-full object-cover"
                                />
                            ) : (
                                <Store className="size-5 text-muted-foreground" />
                            )}
                        </div>

                        <div className="mt-9 space-y-1.5">
                            <p className="leading-tight font-semibold text-foreground transition-colors group-hover:text-primary">
                                {vendor.shop_name}
                            </p>

                            {vendor.description ? (
                                <p className="line-clamp-2 text-xs leading-5 text-muted-foreground">
                                    {vendor.description}
                                </p>
                            ) : null}

                            {vendor.product_count !== undefined ? (
                                <p className="pt-1 text-xs font-medium text-primary">
                                    {vendor.product_count.toLocaleString()}{' '}
                                    product
                                    {vendor.product_count === 1 ? '' : 's'}
                                </p>
                            ) : null}
                        </div>
                    </div>
                </Link>
            </CardContent>
        </Card>
    );
}
