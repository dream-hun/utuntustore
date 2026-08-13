import { Link } from '@inertiajs/react';
import { ImageOff } from 'lucide-react';
import { Money } from '@/components/money';
import { RatingStars } from '@/components/storefront/rating-stars';
import { cn } from '@/lib/utils';
import { show as productShow } from '@/routes/products';

export interface StorefrontProduct {
    id: string;
    name: string;
    slug: string;
    price: number;
    compare_at_price: number | null;
    currency: string;
    primary_image_url: string | null;
    in_stock: boolean;
    rating_average?: number;
    rating_count?: number;
    vendor: { id: string; shop_name: string; slug: string };
}

interface ProductCardProps {
    product: StorefrontProduct;
    className?: string;
}

/**
 * The single product card used by the home page, catalog, category and shop pages.
 *
 * Attribution to the shop is deliberately always visible: on a marketplace the
 * customer is buying from a specific vendor and will pay that vendor at the door,
 * so hiding who they are would be misleading. It takes the slot the kit gives the
 * product's category, since the shop is the more load-bearing fact here.
 */
export function ProductCard({ product, className }: ProductCardProps) {
    const isOnSale =
        product.compare_at_price !== null &&
        product.compare_at_price > product.price;

    const discountPct = isOnSale
        ? Math.round(
              ((product.compare_at_price! - product.price) /
                  product.compare_at_price!) *
                  100,
          )
        : 0;

    return (
        <Link
            href={productShow.url({ product: product.slug })}
            className={cn(
                'group block overflow-hidden rounded-2xl border border-border bg-card transition duration-500 hover:shadow-[0_12px_30px_-12px_oklch(0.2_0.02_250/0.25)] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                className,
            )}
        >
            <div className="relative aspect-square overflow-hidden bg-cream">
                {product.primary_image_url ? (
                    <img
                        src={product.primary_image_url}
                        alt={product.name}
                        loading="lazy"
                        className="absolute inset-0 size-full object-cover transition duration-700 motion-safe:group-hover:scale-105"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center text-muted-foreground">
                        <ImageOff className="size-8" aria-hidden="true" />
                    </div>
                )}

                <div className="absolute top-3 left-3 flex flex-col items-start gap-1.5">
                    {!product.in_stock ? (
                        <span className="rounded-full bg-background/90 px-2.5 py-1 text-[0.65rem] font-semibold backdrop-blur">
                            Out of stock
                        </span>
                    ) : null}
                    {isOnSale && product.in_stock ? (
                        <span className="rounded-full bg-primary px-2.5 py-1 text-[0.65rem] font-semibold text-primary-foreground">
                            −{discountPct}%
                        </span>
                    ) : null}
                </div>
            </div>

            <div className="p-4">
                <div className="truncate text-[0.7rem] font-semibold tracking-[0.12em] text-primary uppercase">
                    {product.vendor.shop_name}
                </div>

                <h3 className="mt-1 truncate font-heading text-base">
                    {product.name}
                </h3>

                {product.rating_average !== undefined &&
                product.rating_count !== undefined &&
                product.rating_count > 0 ? (
                    <RatingStars
                        rating={product.rating_average}
                        count={product.rating_count}
                        size="sm"
                        className="mt-1.5"
                    />
                ) : null}

                <div className="mt-2 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2">
                    <div className="flex min-w-0 items-baseline gap-2">
                        <Money
                            amount={product.price}
                            currency={product.currency}
                            className="font-heading text-lg"
                        />
                        {isOnSale ? (
                            <Money
                                amount={product.compare_at_price!}
                                currency={product.currency}
                                className="truncate text-xs text-muted-foreground line-through"
                            />
                        ) : null}
                    </div>
                    <span
                        className={cn(
                            'shrink-0 text-[0.7rem] font-semibold',
                            product.in_stock
                                ? 'text-primary'
                                : 'text-muted-foreground',
                        )}
                    >
                        {product.in_stock ? 'In stock' : 'Sold out'}
                    </span>
                </div>
            </div>
        </Link>
    );
}

interface ProductGridProps {
    products: StorefrontProduct[];
    className?: string;
}

export function ProductGrid({ products, className }: ProductGridProps) {
    return (
        <div
            className={cn(
                'grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-6',
                className,
            )}
        >
            {products.map((product) => (
                <ProductCard key={product.id} product={product} />
            ))}
        </div>
    );
}
