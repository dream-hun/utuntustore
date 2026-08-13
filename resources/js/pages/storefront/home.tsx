import { Deferred, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Banknote,
    BriefcaseBusiness,
    Gift,
    Heart,
    ShoppingBag,
    Sparkles,
    Store,
    Truck,
} from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { ProductCard } from '@/components/storefront/product-card';
import { ProductGridSkeleton } from '@/components/storefront/product-card-skeleton';
import { PromoBanner } from '@/components/storefront/promo-banner';
import { SectionHeader } from '@/components/storefront/section-header';
import { VendorCard } from '@/components/storefront/vendor-card';
import { Skeleton } from '@/components/ui/skeleton';
import StorefrontLayout from '@/layouts/storefront-layout';
import { shop } from '@/routes';
import { show as categoryShow } from '@/routes/categories';
import type {
    StorefrontCategoryLink,
    StorefrontProductCard,
    StorefrontVendorCard,
} from '@/types/marketplace';

interface FeaturedVendor extends StorefrontVendorCard {
    description: string | null;
}

const image = (name: string): string => `/images/joyful-commerce/${name}`;

/** Rotated over the category tiles — categories are user-created and carry no icon. */
const categoryIcons = [Store, Gift, Sparkles, BriefcaseBusiness, Heart];

export default function Home({
    categories,
    latestProducts,
    featuredVendors,
}: {
    categories?: StorefrontCategoryLink[];
    latestProducts?: StorefrontProductCard[];
    featuredVendors?: FeaturedVendor[];
}) {
    const products = latestProducts ?? [];

    return (
        <StorefrontLayout categories={categories}>
            <Head title="Discover local style" />

            {/* ─── Hero ──────────────────────────────────────────────────── */}
            <section className="mx-auto w-full max-w-[1400px] px-4 pt-6 lg:px-8">
                <div className="relative overflow-hidden rounded-3xl bg-cream">
                    <img
                        src={image('hero-watch.jpg')}
                        alt="A curated selection from local shops"
                        className="absolute inset-0 size-full object-cover"
                    />
                    <div className="absolute inset-0 bg-gradient-to-r from-ink/75 via-ink/40 to-transparent" />
                    <div className="relative z-10 flex min-h-[380px] flex-col justify-between gap-10 p-6 sm:min-h-[460px] sm:p-10 lg:min-h-[560px] lg:p-14">
                        <div className="max-w-2xl">
                            <span className="eyebrow text-white/80">
                                Curated for Rwanda
                            </span>
                            <h1 className="mt-3 text-4xl leading-[0.95] text-white sm:text-6xl lg:text-7xl">
                                Local finds,
                                <br />
                                beautifully
                                <br />
                                delivered.
                            </h1>
                            <p className="mt-5 max-w-md text-sm leading-relaxed text-white/85">
                                Thoughtful products from independent shops
                                around you — ordered in one place, delivered by
                                the people who made and stock them.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Link
                                href={shop.url()}
                                className="inline-flex items-center gap-2 rounded-full bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition hover:opacity-90"
                            >
                                Shop new arrivals
                                <ArrowRight className="size-4" />
                            </Link>
                            <a
                                href="#categories"
                                className="rounded-full border border-white/40 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                            >
                                Explore categories
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            {/* ─── Shop by category ──────────────────────────────────────── */}
            <section
                id="categories"
                aria-labelledby="categories-heading"
                className="mx-auto w-full max-w-[1400px] px-4 py-12 lg:px-8 lg:py-16"
            >
                <div className="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-start">
                    <div>
                        <h2
                            id="categories-heading"
                            className="text-2xl sm:text-3xl"
                        >
                            Shop by category
                        </h2>
                        <Link
                            href={shop.url()}
                            className="mt-2 inline-block text-sm font-semibold text-primary hover:opacity-80"
                        >
                            See all
                        </Link>
                    </div>

                    <Deferred
                        data="categories"
                        fallback={<CategoryGridSkeleton />}
                    >
                        {(categories ?? []).length === 0 ? null : (
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                {(categories ?? []).map((category, index) => {
                                    const Icon =
                                        categoryIcons[
                                            index % categoryIcons.length
                                        ];

                                    return (
                                        <Link
                                            key={category.id}
                                            href={categoryShow.url(category)}
                                            className="flex min-w-0 items-center gap-2.5 rounded-xl border border-border bg-card px-3.5 py-3 text-sm transition hover:border-primary hover:bg-aqua-soft"
                                        >
                                            <span className="grid size-7 shrink-0 place-items-center rounded-lg bg-aqua-soft text-primary">
                                                <Icon className="size-4" />
                                            </span>
                                            <span className="truncate font-medium">
                                                {category.name}
                                            </span>
                                        </Link>
                                    );
                                })}
                            </div>
                        )}
                    </Deferred>
                </div>
            </section>

            {/* ─── New arrivals ──────────────────────────────────────────── */}
            <section
                aria-labelledby="arrivals-heading"
                className="mx-auto w-full max-w-[1400px] px-4 py-12 lg:px-8 lg:py-16"
            >
                <SectionHeader
                    id="arrivals-heading"
                    eyebrow="Fresh in"
                    title="New arrivals"
                    viewAllHref={shop.url()}
                    className="mb-6"
                />
                <Deferred
                    data="latestProducts"
                    fallback={<ProductGridSkeleton count={4} />}
                >
                    {products.length === 0 ? (
                        <EmptyState
                            icon={Store}
                            title="New finds are on their way"
                            description="Check back soon as local shops add their latest products."
                        />
                    ) : (
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-6">
                            {products.slice(0, 4).map((product) => (
                                <ProductCard
                                    key={product.id}
                                    product={product}
                                />
                            ))}
                        </div>
                    )}
                </Deferred>
            </section>

            {/* ─── Promo grid ────────────────────────────────────────────── */}
            <section
                className="mx-auto w-full max-w-[1400px] px-4 pb-4 lg:px-8"
                aria-label="Marketplace highlights"
            >
                <div className="grid gap-4 lg:grid-cols-2">
                    <div className="relative overflow-hidden rounded-3xl bg-primary p-7 text-primary-foreground sm:p-10">
                        <div className="relative z-10 max-w-xs">
                            <span className="eyebrow opacity-80">
                                Made for easy gifting
                            </span>
                            <h2 className="mt-3 text-3xl leading-tight sm:text-4xl">
                                Your city,
                                <br />
                                delivered.
                                <br />
                                One cart, many shops.
                            </h2>
                            <Link
                                href={shop.url()}
                                className="mt-6 inline-flex rounded-full bg-background px-5 py-2.5 text-sm font-semibold text-foreground transition hover:opacity-90"
                            >
                                Shop now
                            </Link>
                        </div>
                        <img
                            src={image('lifestyle-1.jpg')}
                            alt=""
                            loading="lazy"
                            className="pointer-events-none absolute -right-4 -bottom-6 hidden h-[85%] w-1/2 rounded-2xl object-cover sm:block"
                        />
                    </div>

                    <div className="grid gap-4">
                        {[
                            {
                                eyebrow: 'Everyday essentials',
                                title: 'Stock up from shops near you',
                                img: 'watch-2.jpg',
                            },
                            {
                                eyebrow: 'Something unexpected',
                                title: 'Browse what makers just listed',
                                img: 'watch-3.jpg',
                            },
                        ].map((banner) => (
                            <div
                                key={banner.title}
                                className="grid grid-cols-[minmax(0,1fr)_38%] items-center gap-4 overflow-hidden rounded-3xl bg-cream p-6 sm:p-8"
                            >
                                <div className="min-w-0">
                                    <span className="text-xs text-muted-foreground">
                                        {banner.eyebrow}
                                    </span>
                                    <h2 className="mt-1 text-xl sm:text-2xl">
                                        {banner.title}
                                    </h2>
                                    <Link
                                        href={shop.url()}
                                        className="mt-4 inline-flex rounded-full bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground transition hover:opacity-90"
                                    >
                                        Shop now
                                    </Link>
                                </div>
                                <img
                                    src={image(banner.img)}
                                    alt=""
                                    loading="lazy"
                                    className="aspect-square w-full rounded-2xl object-cover"
                                />
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ─── How ordering works ────────────────────────────────────── */}
            <section
                aria-labelledby="how-heading"
                className="mx-auto w-full max-w-[1400px] px-4 py-12 lg:px-8 lg:py-16"
            >
                <SectionHeader
                    id="how-heading"
                    eyebrow="No card needed"
                    title="How ordering works"
                    className="mb-6"
                />
                <div className="grid gap-4 lg:grid-cols-2">
                    <PromoBanner
                        variant="ink"
                        eyebrow="One checkout"
                        title="Order from several shops at once"
                        description="Fill a single cart from as many local shops as you like. We split it into one order per shop, so each can pack and deliver its own items."
                        ctaLabel="Start a cart"
                        ctaHref={shop.url()}
                        image={image('collection-1.jpg')}
                    />
                    <PromoBanner
                        variant="aqua"
                        eyebrow="Pay at the door"
                        title="Cash on delivery, every time"
                        description="Nothing is charged up front. You pay each shop directly, in cash, when it hands over your items — so you only pay for what actually arrives."
                        ctaLabel="Browse products"
                        ctaHref={shop.url()}
                    />
                </div>
            </section>

            {/* ─── More from local shops ─────────────────────────────────── */}
            {products.length > 4 ? (
                <section
                    aria-labelledby="more-heading"
                    className="mx-auto w-full max-w-[1400px] px-4 py-12 lg:px-8 lg:py-16"
                >
                    <SectionHeader
                        id="more-heading"
                        eyebrow="Loved most"
                        title="More from local shops"
                        viewAllHref={shop.url()}
                        className="mb-6"
                    />
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-6">
                        {products.slice(4, 8).map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                </section>
            ) : null}

            {/* ─── Meet the shops ────────────────────────────────────────── */}
            <section
                aria-labelledby="shops-heading"
                className="mx-auto w-full max-w-[1400px] px-4 py-12 lg:px-8 lg:py-16"
            >
                <SectionHeader
                    id="shops-heading"
                    eyebrow="Independent businesses"
                    title="Meet the shops"
                    className="mb-6"
                />
                <Deferred
                    data="featuredVendors"
                    fallback={<VendorGridSkeleton />}
                >
                    {(featuredVendors ?? []).length === 0 ? null : (
                        <div className="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-6">
                            {(featuredVendors ?? []).map((vendor) => (
                                <VendorCard key={vendor.id} vendor={vendor} />
                            ))}
                        </div>
                    )}
                </Deferred>
            </section>

            {/* ─── Trust strip ───────────────────────────────────────────── */}
            <section className="mx-auto w-full max-w-[1400px] px-4 pb-4 lg:px-8">
                <div className="grid gap-5 border-y border-border py-8 text-sm sm:grid-cols-3">
                    <TrustItem
                        icon={<Truck className="size-5" />}
                        title="Delivery by local shops"
                        description="Each vendor delivers directly to you."
                    />
                    <TrustItem
                        icon={<ShoppingBag className="size-5" />}
                        title="One easy checkout"
                        description="Shop from several vendors at once."
                    />
                    <TrustItem
                        icon={<Banknote className="size-5" />}
                        title="Pay on delivery"
                        description="Pay each shop when your order arrives."
                    />
                </div>
            </section>
        </StorefrontLayout>
    );
}

function TrustItem({
    icon,
    title,
    description,
}: {
    icon: React.ReactNode;
    title: string;
    description: string;
}) {
    return (
        <div className="flex gap-3">
            <span className="mt-0.5 text-primary" aria-hidden="true">
                {icon}
            </span>
            <div>
                <p className="font-semibold">{title}</p>
                <p className="mt-1 text-muted-foreground">{description}</p>
            </div>
        </div>
    );
}

function CategoryGridSkeleton() {
    return (
        <div
            className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
            aria-busy="true"
        >
            {Array.from({ length: 8 }).map((_, index) => (
                <Skeleton key={index} className="h-13 rounded-xl" />
            ))}
        </div>
    );
}

function VendorGridSkeleton() {
    return (
        <div
            className="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-6"
            aria-busy="true"
        >
            {Array.from({ length: 4 }).map((_, index) => (
                <Skeleton key={index} className="h-56 rounded-2xl" />
            ))}
        </div>
    );
}
