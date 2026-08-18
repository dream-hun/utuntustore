import { Deferred, Head } from '@inertiajs/react';
import { PackageSearch, Store, Truck } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Image } from '@/components/image';
import { Money } from '@/components/money';
import { PaginationNav } from '@/components/pagination-nav';
import { ProductGrid } from '@/components/storefront/product-card';
import type { StorefrontProduct } from '@/components/storefront/product-card';
import { Skeleton } from '@/components/ui/skeleton';
import StorefrontLayout from '@/layouts/storefront-layout';
import { formatDeliveryEstimate } from '@/lib/format';
import type { Paginated, StorefrontCategoryLink } from '@/types/marketplace';

interface DeliveryAreaRow {
    id: string;
    district: string;
    sector: string | null;
    delivery_fee: number;
    estimated_days_min: number;
    estimated_days_max: number;
}

export default function VendorShop({
    vendor,
    products,
    deliveryAreas,
    navCategories,
}: {
    vendor: {
        id: string;
        shop_name: string;
        slug: string;
        logo_url: string | null;
        banner_url: string | null;
        can_sell: boolean;
        description: string | null;
        delivery_notes: string | null;
        phone: string;
        email: string | null;
    };
    products: Paginated<StorefrontProduct>;
    deliveryAreas?: DeliveryAreaRow[];
    navCategories?: StorefrontCategoryLink[];
}) {
    return (
        <StorefrontLayout categories={navCategories}>
            <Head title={vendor.shop_name} />

            {/* ─── Banner ────────────────────────────────────────────────── */}
            <section className="mx-auto w-full max-w-[1400px] px-4 pt-6 lg:px-8">
                <div className="relative h-44 overflow-hidden rounded-3xl bg-cream sm:h-60">
                    {vendor.banner_url ? (
                        <>
                            <Image
                                src={vendor.banner_url}
                                alt=""
                                priority
                                className="size-full object-cover"
                            />
                            <div className="absolute inset-0 bg-gradient-to-r from-ink/50 to-transparent" />
                        </>
                    ) : null}
                </div>
            </section>

            <div className="mx-auto w-full max-w-[1400px] px-4 pb-12 lg:px-8 lg:pb-16">
                <header className="-mt-12 mb-12 flex flex-wrap items-end gap-5 border-b border-border pb-8">
                    <span className="grid size-24 shrink-0 place-items-center overflow-hidden rounded-2xl border-4 border-background bg-cream">
                        <Image
                            src={vendor.logo_url}
                            alt=""
                            icon={Store}
                            iconClassName="size-8"
                            className="size-full object-cover"
                        />
                    </span>

                    <div className="min-w-0 flex-1 pb-1">
                        <span className="eyebrow text-primary">Local shop</span>
                        <h1 className="mt-1 text-3xl md:text-4xl">
                            {vendor.shop_name}
                        </h1>
                        {vendor.description ? (
                            <p className="mt-3 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                                {vendor.description}
                            </p>
                        ) : null}
                    </div>
                </header>

                <div className="grid gap-10 lg:grid-cols-[280px_minmax(0,1fr)] lg:gap-12">
                    <aside className="space-y-8">
                        <section aria-labelledby="delivery-heading">
                            <h2
                                id="delivery-heading"
                                className="flex items-center gap-2 eyebrow text-muted-foreground"
                            >
                                <Truck
                                    className="size-3.5 text-primary"
                                    aria-hidden="true"
                                />
                                Where this shop delivers
                            </h2>

                            {vendor.delivery_notes ? (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    {vendor.delivery_notes}
                                </p>
                            ) : null}

                            <Deferred
                                data="deliveryAreas"
                                fallback={
                                    <div
                                        className="mt-4 space-y-2"
                                        aria-busy="true"
                                    >
                                        {Array.from({ length: 3 }).map(
                                            (_, index) => (
                                                <Skeleton
                                                    key={index}
                                                    className="h-8 w-full"
                                                />
                                            ),
                                        )}
                                    </div>
                                }
                            >
                                {(deliveryAreas ?? []).length === 0 ? (
                                    <p className="mt-3 text-xs text-muted-foreground">
                                        This shop has not set up delivery areas
                                        yet.
                                    </p>
                                ) : (
                                    <ul className="mt-4 space-y-3">
                                        {(deliveryAreas ?? []).map((area) => (
                                            <li
                                                key={area.id}
                                                className="flex items-start justify-between gap-3 border-b border-border pb-3 text-xs last:border-0"
                                            >
                                                <span className="min-w-0">
                                                    <span className="block font-semibold">
                                                        {area.district}
                                                        <span className="font-normal text-muted-foreground">
                                                            {area.sector
                                                                ? ` · ${area.sector}`
                                                                : ' · all sectors'}
                                                        </span>
                                                    </span>
                                                    <span className="block text-muted-foreground">
                                                        {formatDeliveryEstimate(
                                                            area.estimated_days_min,
                                                            area.estimated_days_max,
                                                        )}
                                                    </span>
                                                </span>
                                                <span className="shrink-0 font-semibold text-primary">
                                                    {area.delivery_fee === 0 ? (
                                                        'Free'
                                                    ) : (
                                                        <Money
                                                            amount={
                                                                area.delivery_fee
                                                            }
                                                        />
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Deferred>
                        </section>

                        <section
                            aria-labelledby="contact-heading"
                            className="rounded-2xl bg-cream p-5"
                        >
                            <h2
                                id="contact-heading"
                                className="eyebrow text-muted-foreground"
                            >
                                Contact
                            </h2>
                            <p className="mt-3 text-sm">{vendor.phone}</p>
                            {vendor.email ? (
                                <p className="text-sm break-all text-muted-foreground">
                                    {vendor.email}
                                </p>
                            ) : null}
                            {!vendor.can_sell ? (
                                <p className="mt-3 text-xs text-destructive">
                                    This shop is not currently accepting orders.
                                </p>
                            ) : null}
                        </section>
                    </aside>

                    <div className="space-y-8">
                        {products.data.length === 0 ? (
                            <EmptyState
                                icon={PackageSearch}
                                title="No products yet"
                                description="This shop has not published anything for sale."
                            />
                        ) : (
                            <>
                                <ProductGrid
                                    products={products.data}
                                    className="lg:grid-cols-3"
                                />
                                <PaginationNav paginator={products} />
                            </>
                        )}
                    </div>
                </div>
            </div>
        </StorefrontLayout>
    );
}
