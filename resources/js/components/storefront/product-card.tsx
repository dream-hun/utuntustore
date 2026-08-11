import { Link } from '@inertiajs/react';
import { ArrowUpRight, ImageOff } from 'lucide-react';
import { Money } from '@/components/money';
import { RatingStars } from '@/components/storefront/rating-stars';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
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
 * so hiding who they are would be misleading.
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
        <Card
            className={cn(
                'group overflow-hidden rounded-2xl border-border/70 bg-card py-0 shadow-sm transition duration-500 motion-safe:hover:-translate-y-1 motion-safe:hover:border-[#168b8f]/30 motion-safe:hover:shadow-[0_18px_35px_-20px_rgba(16,45,54,0.45)]',
                className,
            )}
        >
            <CardContent className="p-0">
                <Link
                    href={productShow.url({ product: product.slug })}
                    className="block focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset"
                >
                    {/* Image */}
                    <div className="relative aspect-square w-full overflow-hidden bg-[#f1f7f6]">
                        {product.primary_image_url ? (
                            <img
                                src={product.primary_image_url}
                                alt={product.name}
                                loading="lazy"
                                className="size-full object-cover transition duration-700 motion-safe:group-hover:scale-105"
                            />
                        ) : (
                            <div className="flex size-full items-center justify-center text-muted-foreground">
                                <ImageOff className="size-8" />
                            </div>
                        )}

                        {/* Badges — top-left */}
                        <div className="absolute top-3 left-3 flex flex-col gap-1.5">
                            {!product.in_stock ? (
                                <Badge
                                    variant="secondary"
                                    className="bg-background/90 text-foreground shadow-sm backdrop-blur"
                                >
                                    Out of stock
                                </Badge>
                            ) : null}
                            {isOnSale && product.in_stock ? (
                                <Badge className="border-0 bg-rose-500 px-2 text-white shadow-sm">
                                    -{discountPct}%
                                </Badge>
                            ) : null}
                        </div>
                        <span className="absolute right-3 bottom-3 flex size-8 translate-y-1 items-center justify-center rounded-full bg-background/90 text-foreground opacity-0 shadow-sm backdrop-blur transition-all group-hover:translate-y-0 group-hover:opacity-100">
                            <ArrowUpRight
                                className="size-4"
                                aria-hidden="true"
                            />
                        </span>
                    </div>

                    {/* Info */}
                    <div className="space-y-2 p-4">
                        <p className="truncate text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                            {product.vendor.shop_name}
                        </p>

                        <p className="line-clamp-2 font-semibold tracking-tight text-foreground">
                            {product.name}
                        </p>

                        {/* Rating */}
                        {product.rating_average !== undefined &&
                        product.rating_count !== undefined &&
                        product.rating_count > 0 ? (
                            <RatingStars
                                rating={product.rating_average}
                                count={product.rating_count}
                                size="sm"
                            />
                        ) : null}

                        {/* Pricing */}
                        <div className="flex flex-wrap items-baseline gap-2 pt-1">
                            <Money
                                amount={product.price}
                                currency={product.currency}
                                className="text-lg font-semibold tracking-tight"
                            />
                            {isOnSale ? (
                                <Money
                                    amount={product.compare_at_price!}
                                    currency={product.currency}
                                    className="text-xs text-muted-foreground line-through"
                                />
                            ) : null}
                        </div>
                    </div>
                </Link>
            </CardContent>
        </Card>
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
                'grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4',
                className,
            )}
        >
            {products.map((product) => (
                <ProductCard key={product.id} product={product} />
            ))}
        </div>
    );
}
