import { Deferred, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BriefcaseBusiness,
    Gift,
    Heart,
    PackageCheck,
    ShoppingBag,
    Sparkles,
    Store,
    Truck,
} from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { ProductCard } from '@/components/storefront/product-card';
import { ProductGridSkeleton } from '@/components/storefront/product-card-skeleton';
import { SectionHeader } from '@/components/storefront/section-header';
import { VendorCard } from '@/components/storefront/vendor-card';
import { Skeleton } from '@/components/ui/skeleton';
import StorefrontLayout from '@/layouts/storefront-layout';
import { show as categoryShow } from '@/routes/categories';
import { shop } from '@/routes';
import type {
    StorefrontCategoryLink,
    StorefrontProductCard,
    StorefrontVendorCard,
} from '@/types/marketplace';

interface FeaturedVendor extends StorefrontVendorCard {
    description: string | null;
}

const joyfulImage = (name: string): string => `/images/joyful-commerce/${name}`;

export default function Home({
    categories,
    latestProducts,
    featuredVendors,
}: {
    categories?: StorefrontCategoryLink[];
    latestProducts?: StorefrontProductCard[];
    featuredVendors?: FeaturedVendor[];
}) {
    return (
        <StorefrontLayout categories={categories}>
            <Head title="Discover local style" />

            <div className="mx-auto w-full max-w-[1400px] space-y-12 px-4 py-6 sm:px-6 lg:space-y-16 lg:px-8">
                <section className="relative isolate overflow-hidden rounded-3xl bg-[#18343c] text-white">
                    <img
                        src={joyfulImage('hero-watch.jpg')}
                        alt="A curated selection from local shops"
                        className="absolute inset-0 size-full object-cover"
                    />
                    <div className="absolute inset-0 bg-gradient-to-r from-[#132e37]/95 via-[#132e37]/65 to-[#132e37]/10" />
                    <div className="relative flex min-h-[29rem] max-w-2xl flex-col justify-between gap-10 p-7 sm:min-h-[33rem] sm:p-11 lg:min-h-[35rem] lg:p-14">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.2em] text-[#8ae4d8] uppercase">
                                Curated for Rwanda
                            </p>
                            <h1 className="mt-4 text-4xl font-semibold tracking-[-0.06em] sm:text-6xl lg:text-7xl">
                                Local finds,
                                <br />
                                beautifully
                                <br />
                                delivered.
                            </h1>
                            <p className="mt-5 max-w-md text-sm leading-7 text-white/80 sm:text-base">
                                Discover thoughtful products from independent
                                shops, all in one easy place.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <Link
                                href={shop.url()}
                                className="inline-flex items-center gap-2 rounded-full bg-[#79d9cb] px-6 py-3 text-sm font-semibold text-[#123039] transition hover:bg-[#9ae9dc]"
                            >
                                Shop new arrivals{' '}
                                <ArrowRight className="size-4" />
                            </Link>
                            <a
                                href="#categories"
                                className="rounded-full border border-white/40 px-6 py-3 text-sm font-semibold transition hover:bg-white/10"
                            >
                                Explore categories
                            </a>
                        </div>
                    </div>
                </section>

                <section id="categories" aria-labelledby="categories-heading">
                    <SectionHeader
                        id="categories-heading"
                        as="h2"
                        title="Shop by category"
                        description="A little something for every part of your day"
                        viewAllHref={shop.url()}
                        viewAllLabel="See all"
                        className="mb-5"
                    />
                    <Deferred
                        data="categories"
                        fallback={<CategoryGridSkeleton />}
                    >
                        {(categories ?? []).length === 0 ? null : (
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                                {(categories ?? []).map((category, index) => (
                                    <CategoryCard
                                        key={category.id}
                                        category={category}
                                        index={index}
                                    />
                                ))}
                            </div>
                        )}
                    </Deferred>
                </section>

                <section aria-labelledby="arrivals-heading">
                    <SectionHeader
                        id="arrivals-heading"
                        as="h2"
                        title="New arrivals"
                        description="Freshly added by local shops"
                        viewAllHref={shop.url()}
                        viewAllLabel="See all"
                        className="mb-5"
                    />
                    <Deferred
                        data="latestProducts"
                        fallback={<ProductGridSkeleton count={8} />}
                    >
                        {(latestProducts ?? []).length === 0 ? (
                            <EmptyState
                                icon={Store}
                                title="New finds are on their way"
                                description="Check back soon as local shops add their latest products."
                            />
                        ) : (
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 lg:gap-6">
                                {(latestProducts ?? [])
                                    .slice(0, 8)
                                    .map((product) => (
                                        <ProductCard
                                            key={product.id}
                                            product={product}
                                        />
                                    ))}
                            </div>
                        )}
                    </Deferred>
                </section>

                <section
                    className="grid gap-4 lg:grid-cols-2"
                    aria-label="Marketplace benefits"
                >
                    <PromoCard
                        eyebrow="Made for easy gifting"
                        title="Discover something unexpectedly perfect."
                        description="A changing selection of goods from makers and shops around you."
                        image="lifestyle-1.jpg"
                        className="bg-[#16323a] text-white"
                    />
                    <PromoCard
                        eyebrow="Shop with confidence"
                        title="The local marketplace, made simple."
                        description="Order from trusted shops, then pay only when your order arrives."
                        image="collection-1.jpg"
                        className="bg-[#e4f4f1] text-[#17353c]"
                    />
                </section>

                <section aria-labelledby="shops-heading">
                    <SectionHeader
                        id="shops-heading"
                        as="h2"
                        title="Meet the shops"
                        description="Independent businesses worth knowing"
                        className="mb-5"
                    />
                    <Deferred
                        data="featuredVendors"
                        fallback={<VendorGridSkeleton />}
                    >
                        {(featuredVendors ?? []).length === 0 ? null : (
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 lg:gap-6">
                                {(featuredVendors ?? []).map((vendor) => (
                                    <VendorCard
                                        key={vendor.id}
                                        vendor={vendor}
                                    />
                                ))}
                            </div>
                        )}
                    </Deferred>
                </section>

                <section className="grid overflow-hidden rounded-3xl bg-[#eef9f8] sm:grid-cols-[1.15fr_0.85fr]">
                    <div className="p-7 sm:p-10">
                        <p className="text-xs font-semibold tracking-[0.18em] text-[#198e92] uppercase">
                            More than a marketplace
                        </p>
                        <h2 className="mt-3 max-w-md text-3xl font-semibold tracking-tight text-[#17353c] sm:text-4xl">
                            Good things from close to home.
                        </h2>
                        <p className="mt-4 max-w-lg leading-7 text-[#527177]">
                            Support the businesses around you while enjoying a
                            smoother way to browse, order, and receive what you
                            love.
                        </p>
                        <Link
                            href={shop.url()}
                            className="mt-7 inline-flex items-center gap-2 text-sm font-semibold text-[#168b8f] hover:text-[#0e7073]"
                        >
                            Start exploring <ArrowRight className="size-4" />
                        </Link>
                    </div>
                    <img
                        src={joyfulImage('watch-3.jpg')}
                        alt=""
                        className="size-full min-h-60 object-cover"
                        loading="lazy"
                    />
                </section>

                <div className="grid gap-5 border-y border-border py-7 text-sm sm:grid-cols-3">
                    <TrustItem
                        icon={<Truck className="size-5" />}
                        title="Delivery by local shops"
                        description="Each vendor delivers directly to you."
                    />
                    <TrustItem
                        icon={<ShoppingBag className="size-5" />}
                        title="One easy checkout"
                        description="Shop from multiple vendors at once."
                    />
                    <TrustItem
                        icon={<PackageCheck className="size-5" />}
                        title="Pay on delivery"
                        description="Pay each shop when your order arrives."
                    />
                </div>
            </div>
        </StorefrontLayout>
    );
}

function CategoryCard({
    category,
    index,
}: {
    category: StorefrontCategoryLink;
    index: number;
}) {
    const accents = [
        'bg-[#e9f8f6] text-[#168b8f]',
        'bg-[#fff4df] text-[#bd7a16]',
        'bg-[#fce9e4] text-[#c96355]',
        'bg-[#ebeaff] text-[#7061b5]',
    ];
    const icons = [Store, Gift, Sparkles, BriefcaseBusiness, Heart];
    const Icon = icons[index % icons.length];

    return (
        <Link
            href={categoryShow.url(category)}
            className="group flex min-h-30 flex-col justify-between rounded-2xl border border-border/70 bg-card p-4 transition hover:-translate-y-1 hover:shadow-md"
        >
            <span
                className={`flex size-10 items-center justify-center rounded-xl ${accents[index % accents.length]}`}
            >
                <Icon className="size-5" />
            </span>
            <span className="mt-4 text-sm font-semibold group-hover:text-[#168b8f]">
                {category.name}
            </span>
        </Link>
    );
}

function PromoCard({
    eyebrow,
    title,
    description,
    image,
    className,
}: {
    eyebrow: string;
    title: string;
    description: string;
    image: string;
    className: string;
}) {
    return (
        <article
            className={`relative min-h-70 overflow-hidden rounded-3xl p-7 sm:p-9 ${className}`}
        >
            <img
                src={joyfulImage(image)}
                alt=""
                className="absolute inset-y-0 right-0 hidden w-1/2 object-cover opacity-50 sm:block"
                loading="lazy"
            />
            <div className="relative z-10 max-w-xs">
                <p className="text-xs font-semibold tracking-[0.16em] uppercase opacity-70">
                    {eyebrow}
                </p>
                <h2 className="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">
                    {title}
                </h2>
                <p className="mt-3 text-sm leading-6 opacity-75">
                    {description}
                </p>
                <Link
                    href={shop.url()}
                    className="mt-6 inline-flex items-center gap-2 text-sm font-semibold hover:underline"
                >
                    Shop now <ArrowRight className="size-4" />
                </Link>
            </div>
        </article>
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
            <span className="mt-0.5 text-[#168b8f]">{icon}</span>
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
            className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
            aria-busy="true"
        >
            {Array.from({ length: 6 }).map((_, index) => (
                <Skeleton key={index} className="h-30 rounded-2xl" />
            ))}
        </div>
    );
}
function VendorGridSkeleton() {
    return (
        <div
            className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4"
            aria-busy="true"
        >
            {Array.from({ length: 4 }).map((_, index) => (
                <Skeleton key={index} className="h-48 rounded-2xl" />
            ))}
        </div>
    );
}
