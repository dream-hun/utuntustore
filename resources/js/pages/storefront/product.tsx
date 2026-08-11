import { Deferred, Head, Link, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ImageOff,
    MessageSquare,
    Package,
    Store,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import { Money } from '@/components/money';
import { RatingStars } from '@/components/storefront/rating-stars';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import StorefrontLayout from '@/layouts/storefront-layout';
import { formatDate } from '@/lib/format';
import { shop } from '@/routes';
import { store as cartStore } from '@/routes/cart';
import { show as categoryShow } from '@/routes/categories';
import { show as vendorShow } from '@/routes/vendors';
import type { Paginated } from '@/types/marketplace';

interface Variant {
    id: string;
    name: string;
    sku: string | null;
    price: number;
    stock_quantity: number;
}

interface ProductDetail {
    id: string;
    name: string;
    slug: string;
    price: number;
    compare_at_price: number | null;
    currency: string;
    primary_image_url: string | null;
    in_stock: boolean;
    description: string | null;
    short_description: string | null;
    sku: string | null;
    stock_quantity: number;
    is_low_stock: boolean;
    category: { id: string; name: string; slug: string };
    images: {
        id: string;
        thumb_url: string;
        web_url: string;
        alt: string | null;
    }[];
    variants: Variant[];
    vendor: { id: string; shop_name: string; slug: string };
}

interface ReviewRow {
    id: string;
    rating: number;
    title: string | null;
    comment: string | null;
    author_name: string;
    created_at: string;
}

export default function ProductPage({
    product,
    vendor,
    rating,
    reviews,
}: {
    product: ProductDetail;
    vendor: {
        id: string;
        shop_name: string;
        slug: string;
        logo_url: string | null;
        can_sell: boolean;
        description: string | null;
        delivery_notes: string | null;
    };
    rating: { average: number; count: number };
    reviews?: Paginated<ReviewRow>;
}) {
    const [activeImage, setActiveImage] = useState<string | null>(
        product.images[0]?.web_url ?? product.primary_image_url,
    );

    const [variantId, setVariantId] = useState<string | null>(
        product.variants[0]?.id ?? null,
    );

    const selectedVariant =
        product.variants.find((v) => v.id === variantId) ?? null;
    const price = selectedVariant?.price ?? product.price;
    const stock = selectedVariant?.stock_quantity ?? product.stock_quantity;

    const isOnSale =
        product.compare_at_price !== null && product.compare_at_price > price;

    const discountPct = isOnSale
        ? Math.round(
              ((product.compare_at_price! - price) /
                  product.compare_at_price!) *
                  100,
          )
        : 0;

    const form = useForm({
        product: product.id,
        variant: variantId,
        quantity: 1,
    });

    const addToCart = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, variant: variantId }));
        form.post(cartStore.url(), { preserveScroll: true });
    };

    return (
        <StorefrontLayout>
            <Head title={product.name} />

            <div className="mx-auto w-full max-w-[1400px] px-4 py-8 sm:px-6 lg:px-8">
                {/* Breadcrumb */}
                <nav
                    aria-label="Breadcrumb"
                    className="mb-6 flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <Link
                        href={shop.url()}
                        className="inline-flex items-center gap-1 transition-colors hover:text-foreground"
                    >
                        <ChevronLeft className="size-4" aria-hidden="true" />
                        Shop
                    </Link>
                    <span aria-hidden="true">/</span>
                    <Link
                        href={categoryShow.url({
                            category: product.category.slug,
                        })}
                        className="transition-colors hover:text-foreground"
                    >
                        {product.category.name}
                    </Link>
                    <span aria-hidden="true">/</span>
                    <span
                        className="max-w-[200px] truncate font-medium text-foreground"
                        aria-current="page"
                    >
                        {product.name}
                    </span>
                </nav>

                {/* Main product area */}
                <div className="grid gap-10 lg:grid-cols-2 lg:gap-16">
                    {/* Left — Image gallery */}
                    <div className="space-y-3">
                        <div className="relative aspect-[4/5] w-full overflow-hidden rounded-3xl bg-[#f1f7f6]">
                            {activeImage ? (
                                <img
                                    src={activeImage}
                                    alt={product.name}
                                    className="size-full object-cover"
                                />
                            ) : (
                                <div className="flex size-full items-center justify-center text-muted-foreground">
                                    <ImageOff
                                        className="size-12"
                                        aria-hidden="true"
                                    />
                                </div>
                            )}

                            {/* Sale ribbon */}
                            {isOnSale ? (
                                <div className="absolute top-4 left-4">
                                    <Badge className="border-rose-500/20 bg-rose-500 px-2.5 py-1 text-sm text-white">
                                        -{discountPct}% OFF
                                    </Badge>
                                </div>
                            ) : null}
                        </div>

                        {/* Thumbnail strip */}
                        {product.images.length > 1 ? (
                            <div
                                className="flex gap-2 overflow-x-auto pb-1"
                                role="group"
                                aria-label="Product images"
                            >
                                {product.images.map((image) => {
                                    const isActive =
                                        activeImage === image.web_url;

                                    return (
                                        <button
                                            key={image.id}
                                            type="button"
                                            onClick={() =>
                                                setActiveImage(image.web_url)
                                            }
                                            aria-label={
                                                image.alt ??
                                                `View image ${product.images.indexOf(image) + 1}`
                                            }
                                            aria-pressed={isActive}
                                            className={
                                                'size-16 shrink-0 overflow-hidden rounded-lg border-2 transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ' +
                                                (isActive
                                                    ? 'border-primary shadow-sm'
                                                    : 'border-transparent opacity-70 hover:opacity-100')
                                            }
                                        >
                                            <img
                                                src={image.thumb_url}
                                                alt={image.alt ?? product.name}
                                                className="size-full object-cover"
                                            />
                                        </button>
                                    );
                                })}
                            </div>
                        ) : null}
                    </div>

                    {/* Right — Product info & actions */}
                    <div className="space-y-5">
                        {/* Category + name */}
                        <div>
                            <Link
                                href={categoryShow.url({
                                    category: product.category.slug,
                                })}
                                className="text-xs font-medium tracking-wider text-primary uppercase hover:underline"
                            >
                                {product.category.name}
                            </Link>
                            <h1 className="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">
                                {product.name}
                            </h1>

                            {rating.count > 0 ? (
                                <div className="mt-2">
                                    <RatingStars
                                        rating={rating.average}
                                        count={rating.count}
                                        size="md"
                                    />
                                </div>
                            ) : null}
                        </div>

                        {/* Pricing */}
                        <div className="flex flex-wrap items-baseline gap-3">
                            <Money
                                amount={price}
                                currency={product.currency}
                                className="text-3xl font-bold"
                            />
                            {isOnSale ? (
                                <>
                                    <Money
                                        amount={product.compare_at_price!}
                                        currency={product.currency}
                                        className="text-lg text-muted-foreground line-through"
                                    />
                                    <span className="rounded-md bg-rose-100 px-2 py-0.5 text-sm font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">
                                        Save {discountPct}%
                                    </span>
                                </>
                            ) : null}
                        </div>

                        {/* Short description */}
                        {product.short_description ? (
                            <p className="leading-relaxed text-muted-foreground">
                                {product.short_description}
                            </p>
                        ) : null}

                        {/* Variants */}
                        {product.variants.length > 0 ? (
                            <div className="space-y-2">
                                <Label className="text-sm font-semibold">
                                    Option
                                </Label>
                                <div
                                    className="flex flex-wrap gap-2"
                                    role="group"
                                    aria-label="Product options"
                                >
                                    {product.variants.map((variant) => {
                                        const isSelected =
                                            variantId === variant.id;
                                        const isOos =
                                            variant.stock_quantity < 1;

                                        return (
                                            <Button
                                                key={variant.id}
                                                type="button"
                                                size="sm"
                                                variant={
                                                    isSelected
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                disabled={isOos}
                                                aria-pressed={isSelected}
                                                onClick={() =>
                                                    setVariantId(variant.id)
                                                }
                                                className={
                                                    isOos
                                                        ? 'line-through opacity-40'
                                                        : ''
                                                }
                                            >
                                                {variant.name}
                                            </Button>
                                        );
                                    })}
                                </div>
                            </div>
                        ) : null}

                        {/* Stock status */}
                        <div>
                            {stock < 1 ? (
                                <Badge variant="secondary" className="text-sm">
                                    Out of stock
                                </Badge>
                            ) : product.is_low_stock ? (
                                <Badge className="border-amber-500/20 bg-amber-500/10 text-sm text-amber-700 dark:text-amber-300">
                                    Only {stock} left — order soon
                                </Badge>
                            ) : (
                                <Badge className="border-emerald-500/20 bg-emerald-500/10 text-sm text-emerald-700 dark:text-emerald-300">
                                    In stock
                                </Badge>
                            )}
                        </div>

                        {/* Add to cart */}
                        <form
                            onSubmit={addToCart}
                            className="flex items-center gap-3"
                            aria-label="Add to cart"
                        >
                            <div className="space-y-1">
                                <Label
                                    htmlFor="quantity"
                                    className="text-xs text-muted-foreground"
                                >
                                    Qty
                                </Label>
                                <Input
                                    id="quantity"
                                    type="number"
                                    min={1}
                                    max={Math.max(stock, 1)}
                                    value={form.data.quantity}
                                    onChange={(event) =>
                                        form.setData(
                                            'quantity',
                                            Number(event.target.value),
                                        )
                                    }
                                    className="w-20"
                                    disabled={stock < 1 || !vendor.can_sell}
                                />
                            </div>
                            <Button
                                type="submit"
                                size="lg"
                                className="flex-1"
                                disabled={
                                    stock < 1 ||
                                    !vendor.can_sell ||
                                    form.processing
                                }
                            >
                                {form.processing ? (
                                    <Spinner className="mr-2" />
                                ) : null}
                                Add to cart
                            </Button>
                        </form>

                        {!vendor.can_sell ? (
                            <p className="rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground">
                                This shop is not currently accepting orders.
                            </p>
                        ) : null}

                        <Separator />

                        {/* Vendor card */}
                        <Card className="border-muted">
                            <CardContent className="flex items-start gap-4 py-4">
                                <Link
                                    href={vendorShow.url({
                                        vendor: vendor.slug,
                                    })}
                                    aria-label={`Visit ${vendor.shop_name}`}
                                >
                                    <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-muted transition-all hover:ring-2 hover:ring-ring">
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
                                </Link>
                                <div className="min-w-0 flex-1">
                                    <Link
                                        href={vendorShow.url({
                                            vendor: vendor.slug,
                                        })}
                                        className="font-semibold transition-colors hover:text-primary"
                                    >
                                        {vendor.shop_name}
                                    </Link>
                                    {vendor.delivery_notes ? (
                                        <p className="mt-1 flex items-start gap-1.5 text-xs text-muted-foreground">
                                            <Truck
                                                className="mt-0.5 size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            {vendor.delivery_notes}
                                        </p>
                                    ) : null}
                                    <p className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Package
                                            className="size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        You pay this shop in cash on delivery
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Full description */}
                {product.description ? (
                    <section
                        className="mt-12 max-w-3xl"
                        aria-labelledby="description-heading"
                    >
                        <h2
                            id="description-heading"
                            className="mb-4 text-xl font-semibold"
                        >
                            Description
                        </h2>
                        <div className="prose prose-sm dark:prose-invert max-w-none text-muted-foreground">
                            <p className="leading-relaxed whitespace-pre-line">
                                {product.description}
                            </p>
                        </div>
                    </section>
                ) : null}

                {/* Reviews */}
                <section
                    className="mt-12 max-w-3xl"
                    aria-labelledby="reviews-heading"
                >
                    <div className="mb-6 flex items-center justify-between">
                        <div>
                            <h2
                                id="reviews-heading"
                                className="text-xl font-semibold"
                            >
                                Customer reviews
                            </h2>
                            {rating.count > 0 ? (
                                <div className="mt-1 flex items-center gap-3">
                                    <RatingStars
                                        rating={rating.average}
                                        size="lg"
                                        showCount={false}
                                    />
                                    <span className="text-2xl font-bold tabular-nums">
                                        {rating.average.toFixed(1)}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        ({rating.count.toLocaleString()} review
                                        {rating.count === 1 ? '' : 's'})
                                    </span>
                                </div>
                            ) : null}
                        </div>
                    </div>

                    <Deferred
                        data="reviews"
                        fallback={
                            <div
                                className="space-y-4"
                                aria-busy="true"
                                aria-label="Loading reviews…"
                            >
                                {Array.from({ length: 3 }).map((_, index) => (
                                    <div
                                        key={index}
                                        className="space-y-2 rounded-xl border border-border p-4"
                                        aria-hidden="true"
                                    >
                                        <Skeleton className="h-4 w-32" />
                                        <Skeleton className="h-3 w-full" />
                                        <Skeleton className="h-3 w-3/4" />
                                    </div>
                                ))}
                            </div>
                        }
                    >
                        {(reviews?.data ?? []).length === 0 ? (
                            <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border py-12 text-center">
                                <MessageSquare
                                    className="size-8 text-muted-foreground/50"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p className="font-medium">
                                        No reviews yet
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Only customers who received this product
                                        can review it.
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {(reviews?.data ?? []).map((review) => (
                                    <ReviewCard
                                        key={review.id}
                                        review={review}
                                    />
                                ))}
                            </div>
                        )}
                    </Deferred>
                </section>
            </div>
        </StorefrontLayout>
    );
}

// ─── Review card ──────────────────────────────────────────────────────────────

function ReviewCard({ review }: { review: ReviewRow }) {
    return (
        <article className="space-y-3 rounded-xl border border-border bg-card p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="space-y-1">
                    <RatingStars
                        rating={review.rating}
                        size="sm"
                        showCount={false}
                    />
                    {review.title ? (
                        <p className="font-semibold">{review.title}</p>
                    ) : null}
                </div>
                <span className="text-xs text-muted-foreground">
                    {review.author_name} · {formatDate(review.created_at)}
                </span>
            </div>

            {review.comment ? (
                <p className="text-sm leading-relaxed text-muted-foreground">
                    {review.comment}
                </p>
            ) : null}
        </article>
    );
}
