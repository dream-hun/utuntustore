import { Deferred, Head, Link, useForm } from '@inertiajs/react';
import {
    Banknote,
    ImageOff,
    MessageSquare,
    Minus,
    Package,
    Plus,
    Store,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import { Money } from '@/components/money';
import { ProductGrid } from '@/components/storefront/product-card';
import type { StorefrontProduct } from '@/components/storefront/product-card';
import { ProductGridSkeleton } from '@/components/storefront/product-card-skeleton';
import { RatingStars } from '@/components/storefront/rating-stars';
import { SectionHeader } from '@/components/storefront/section-header';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { openCartDrawer } from '@/hooks/use-cart-drawer';
import StorefrontLayout from '@/layouts/storefront-layout';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { home, shop } from '@/routes';
import { store as cartStore } from '@/routes/cart';
import { show as categoryShow } from '@/routes/categories';
import { show as vendorShow } from '@/routes/vendors';
import type { Paginated, StorefrontCategoryLink } from '@/types/marketplace';

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
    related,
    navCategories,
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
    related?: StorefrontProduct[];
    navCategories?: StorefrontCategoryLink[];
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

    const setQuantity = (quantity: number) => {
        form.setData(
            'quantity',
            Math.min(Math.max(quantity, 1), Math.max(stock, 1)),
        );
    };

    const addToCart = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, variant: variantId }));
        form.post(cartStore.url(), {
            preserveScroll: true,
            preserveState: true,
            // Show what just landed in the bag rather than leaving the customer to
            // work out whether the click registered.
            onSuccess: openCartDrawer,
        });
    };

    const canOrder = stock > 0 && vendor.can_sell;

    return (
        <StorefrontLayout
            categories={navCategories}
            activeCategorySlug={product.category.slug}
        >
            <Head title={product.name} />

            <div className="mx-auto w-full max-w-[1400px] px-4 py-8 lg:px-8">
                {/* Breadcrumb */}
                <nav
                    aria-label="Breadcrumb"
                    className="text-sm font-semibold text-muted-foreground"
                >
                    <Link href={home.url()} className="hover:text-primary">
                        Home
                    </Link>
                    <span className="mx-2" aria-hidden="true">
                        /
                    </span>
                    <Link href={shop.url()} className="hover:text-primary">
                        Shop
                    </Link>
                    <span className="mx-2" aria-hidden="true">
                        /
                    </span>
                    <Link
                        href={categoryShow.url({
                            category: product.category.slug,
                        })}
                        className="hover:text-primary"
                    >
                        {product.category.name}
                    </Link>
                    <span className="mx-2" aria-hidden="true">
                        /
                    </span>
                    <span className="text-foreground" aria-current="page">
                        {product.name}
                    </span>
                </nav>
            </div>

            <div className="mx-auto grid w-full max-w-[1400px] gap-12 px-4 lg:grid-cols-2 lg:gap-20 lg:px-8">
                {/* ─── Gallery ───────────────────────────────────────────── */}
                <div
                    className={cn(
                        'grid gap-4',
                        product.images.length > 1
                            ? 'grid-cols-[80px_minmax(0,1fr)]'
                            : 'grid-cols-1',
                    )}
                >
                    {product.images.length > 1 ? (
                        <div
                            className="flex flex-col gap-3"
                            role="group"
                            aria-label="Product images"
                        >
                            {product.images.map((image, index) => {
                                const isActive = activeImage === image.web_url;

                                return (
                                    <button
                                        key={image.id}
                                        type="button"
                                        onClick={() =>
                                            setActiveImage(image.web_url)
                                        }
                                        aria-label={
                                            image.alt ??
                                            `View image ${index + 1}`
                                        }
                                        aria-pressed={isActive}
                                        className={cn(
                                            'aspect-square overflow-hidden rounded-2xl bg-cream transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            isActive
                                                ? 'ring-1 ring-foreground'
                                                : 'opacity-70 hover:opacity-100',
                                        )}
                                    >
                                        <img
                                            src={image.thumb_url}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                    </button>
                                );
                            })}
                        </div>
                    ) : null}

                    <div className="relative aspect-[4/5] overflow-hidden rounded-2xl bg-cream">
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

                        {isOnSale ? (
                            <span className="absolute top-4 left-4 rounded-full bg-primary px-3 py-1 text-xs font-semibold text-primary-foreground">
                                −{discountPct}%
                            </span>
                        ) : null}
                    </div>
                </div>

                {/* ─── Details ───────────────────────────────────────────── */}
                <div className="lg:pt-8">
                    <Link
                        href={categoryShow.url({
                            category: product.category.slug,
                        })}
                        className="eyebrow text-primary hover:opacity-80"
                    >
                        {product.category.name}
                    </Link>

                    <h1 className="mt-3 text-3xl leading-none md:text-4xl">
                        {product.name}
                    </h1>

                    {rating.count > 0 ? (
                        <RatingStars
                            rating={rating.average}
                            count={rating.count}
                            size="md"
                            className="mt-3"
                        />
                    ) : null}

                    <div className="mt-4 flex flex-wrap items-baseline gap-3">
                        <Money
                            amount={price}
                            currency={product.currency}
                            className="font-heading text-2xl"
                        />
                        {isOnSale ? (
                            <Money
                                amount={product.compare_at_price!}
                                currency={product.currency}
                                className="text-muted-foreground line-through"
                            />
                        ) : null}
                    </div>

                    {product.short_description ? (
                        <p className="mt-8 leading-relaxed text-muted-foreground">
                            {product.short_description}
                        </p>
                    ) : null}

                    {/* Variants */}
                    {product.variants.length > 0 ? (
                        <div className="mt-8">
                            <p className="mb-3 eyebrow text-muted-foreground">
                                Option
                            </p>
                            <div
                                className="flex flex-wrap gap-2"
                                role="group"
                                aria-label="Product options"
                            >
                                {product.variants.map((variant) => {
                                    const isSelected = variantId === variant.id;
                                    const isOos = variant.stock_quantity < 1;

                                    return (
                                        <button
                                            key={variant.id}
                                            type="button"
                                            disabled={isOos}
                                            aria-pressed={isSelected}
                                            onClick={() =>
                                                setVariantId(variant.id)
                                            }
                                            className={cn(
                                                'rounded-full border px-4 py-2 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                isSelected
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border bg-card hover:border-primary hover:bg-aqua-soft',
                                                isOos
                                                    ? 'cursor-not-allowed line-through opacity-40'
                                                    : null,
                                            )}
                                        >
                                            {variant.name}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    ) : null}

                    {/* Stock */}
                    <p className="mt-6 text-sm font-semibold">
                        {stock < 1 ? (
                            <span className="text-muted-foreground">
                                Out of stock
                            </span>
                        ) : product.is_low_stock ? (
                            <span className="text-gold">
                                Only {stock} left — order soon
                            </span>
                        ) : (
                            <span className="text-primary">In stock</span>
                        )}
                    </p>

                    {/* Add to cart */}
                    <form
                        onSubmit={addToCart}
                        className="mt-6 flex items-center gap-4"
                        aria-label="Add to cart"
                    >
                        <div className="flex items-center rounded-full border border-border">
                            <button
                                type="button"
                                className="p-3 disabled:opacity-40"
                                onClick={() =>
                                    setQuantity(form.data.quantity - 1)
                                }
                                disabled={!canOrder || form.data.quantity <= 1}
                                aria-label="Decrease quantity"
                            >
                                <Minus className="size-3" />
                            </button>
                            <span
                                className="w-10 text-center text-sm tabular-nums"
                                aria-live="polite"
                            >
                                {form.data.quantity}
                            </span>
                            <button
                                type="button"
                                className="p-3 disabled:opacity-40"
                                onClick={() =>
                                    setQuantity(form.data.quantity + 1)
                                }
                                disabled={
                                    !canOrder || form.data.quantity >= stock
                                }
                                aria-label="Increase quantity"
                            >
                                <Plus className="size-3" />
                            </button>
                        </div>

                        <button
                            type="submit"
                            disabled={!canOrder || form.processing}
                            className="inline-flex flex-1 items-center justify-center gap-2 rounded-full bg-primary py-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                        >
                            {form.processing ? <Spinner /> : null}
                            Add to bag —{' '}
                            <Money
                                amount={price * form.data.quantity}
                                currency={product.currency}
                            />
                        </button>
                    </form>

                    {!vendor.can_sell ? (
                        <p className="mt-4 rounded-2xl bg-muted px-4 py-3 text-sm text-muted-foreground">
                            This shop is not currently accepting orders.
                        </p>
                    ) : null}

                    {/* Shop */}
                    <div className="mt-10 border-t border-border pt-8">
                        <p className="mb-4 eyebrow text-muted-foreground">
                            Sold and delivered by
                        </p>
                        <div className="flex items-start gap-4">
                            <Link
                                href={vendorShow.url({ vendor: vendor.slug })}
                                aria-label={`Visit ${vendor.shop_name}`}
                                className="grid size-12 shrink-0 place-items-center overflow-hidden rounded-2xl bg-cream transition hover:ring-2 hover:ring-primary"
                            >
                                {vendor.logo_url ? (
                                    <img
                                        src={vendor.logo_url}
                                        alt=""
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <Store className="size-5 text-muted-foreground" />
                                )}
                            </Link>
                            <div className="min-w-0 flex-1">
                                <Link
                                    href={vendorShow.url({
                                        vendor: vendor.slug,
                                    })}
                                    className="font-heading text-base transition-colors hover:text-primary"
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
                        </div>
                    </div>

                    {/* Promises */}
                    <div className="mt-10 grid grid-cols-3 gap-4 text-xs">
                        {[
                            { icon: Truck, label: 'Delivered by the shop' },
                            { icon: Banknote, label: 'Cash on delivery' },
                            { icon: Package, label: 'Checked before handover' },
                        ].map(({ icon: Icon, label }) => (
                            <div
                                key={label}
                                className="flex flex-col items-start gap-2 border-t border-border pt-4"
                            >
                                <Icon
                                    className="size-4 text-primary"
                                    aria-hidden="true"
                                />
                                <span>{label}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* ─── Description ───────────────────────────────────────────── */}
            {product.description ? (
                <section
                    className="mx-auto mt-16 w-full max-w-[1400px] px-4 lg:px-8"
                    aria-labelledby="description-heading"
                >
                    <div className="max-w-3xl border-t border-border pt-10">
                        <h2 id="description-heading" className="text-2xl">
                            Description
                        </h2>
                        <p className="mt-4 leading-relaxed whitespace-pre-line text-muted-foreground">
                            {product.description}
                        </p>
                    </div>
                </section>
            ) : null}

            {/* ─── You may also like ─────────────────────────────────────── */}
            <section
                className="mx-auto mt-16 w-full max-w-[1400px] px-4 lg:px-8"
                aria-labelledby="related-heading"
            >
                <div className="border-t border-border pt-10">
                    <SectionHeader
                        id="related-heading"
                        eyebrow="More in this category"
                        title="You may also like"
                        viewAllHref={categoryShow.url({
                            category: product.category.slug,
                        })}
                        className="mb-6"
                    />
                    <Deferred
                        data="related"
                        fallback={<ProductGridSkeleton count={4} />}
                    >
                        {(related ?? []).length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nothing else is listed in{' '}
                                {product.category.name} just yet.
                            </p>
                        ) : (
                            <ProductGrid products={related ?? []} />
                        )}
                    </Deferred>
                </div>
            </section>

            {/* ─── Reviews ───────────────────────────────────────────────── */}
            <section
                className="mx-auto mt-16 w-full max-w-[1400px] px-4 pb-8 lg:px-8"
                aria-labelledby="reviews-heading"
            >
                <div className="max-w-3xl border-t border-border pt-10">
                    <h2 id="reviews-heading" className="text-2xl">
                        Customer reviews
                    </h2>

                    {rating.count > 0 ? (
                        <div className="mt-3 flex items-center gap-3">
                            <RatingStars
                                rating={rating.average}
                                size="lg"
                                showCount={false}
                            />
                            <span className="font-heading text-2xl">
                                {rating.average.toFixed(1)}
                            </span>
                            <span className="text-sm text-muted-foreground">
                                ({rating.count.toLocaleString()} review
                                {rating.count === 1 ? '' : 's'})
                            </span>
                        </div>
                    ) : null}

                    <div className="mt-8">
                        <Deferred
                            data="reviews"
                            fallback={
                                <div
                                    className="space-y-4"
                                    aria-busy="true"
                                    aria-label="Loading reviews…"
                                >
                                    {Array.from({ length: 3 }).map(
                                        (_, index) => (
                                            <div
                                                key={index}
                                                className="space-y-2 card-soft p-5"
                                                aria-hidden="true"
                                            >
                                                <Skeleton className="h-4 w-32" />
                                                <Skeleton className="h-3 w-full" />
                                                <Skeleton className="h-3 w-3/4" />
                                            </div>
                                        ),
                                    )}
                                </div>
                            }
                        >
                            {(reviews?.data ?? []).length === 0 ? (
                                <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border py-12 text-center">
                                    <MessageSquare
                                        className="size-8 text-muted-foreground/50"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <p className="font-semibold">
                                            No reviews yet
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Only customers who received this
                                            product can review it.
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
                    </div>
                </div>
            </section>
        </StorefrontLayout>
    );
}

// ─── Review card ──────────────────────────────────────────────────────────────

function ReviewCard({ review }: { review: ReviewRow }) {
    return (
        <article className="space-y-3 card-soft p-5">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="space-y-1">
                    <RatingStars
                        rating={review.rating}
                        size="sm"
                        showCount={false}
                    />
                    {review.title ? (
                        <p className="font-heading text-base">{review.title}</p>
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
