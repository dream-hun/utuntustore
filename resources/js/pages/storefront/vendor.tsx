import { Deferred, Head } from '@inertiajs/react';
import { PackageSearch, Store, Truck } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Money } from '@/components/money';
import { PaginationNav } from '@/components/pagination-nav';
import { ProductGrid } from '@/components/storefront/product-card';
import type { StorefrontProduct } from '@/components/storefront/product-card';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import StorefrontLayout from '@/layouts/storefront-layout';
import { formatDeliveryEstimate } from '@/lib/format';
import type { Paginated } from '@/types/marketplace';

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
}) {
    return (
        <StorefrontLayout>
            <Head title={vendor.shop_name} />

            <div className="h-40 w-full bg-muted sm:h-56">
                {vendor.banner_url ? (
                    <img
                        src={vendor.banner_url}
                        alt={vendor.shop_name}
                        className="size-full object-cover"
                    />
                ) : null}
            </div>

            <div className="mx-auto w-full max-w-7xl px-4 pb-12 sm:px-6 lg:px-8">
                <header className="-mt-10 mb-8 flex flex-wrap items-end gap-4">
                    <div className="flex size-20 items-center justify-center overflow-hidden rounded-xl border-4 border-background bg-background shadow-sm">
                        {vendor.logo_url ? (
                            <img
                                src={vendor.logo_url}
                                alt={vendor.shop_name}
                                className="size-full object-cover"
                            />
                        ) : (
                            <Store className="size-8 text-muted-foreground" />
                        )}
                    </div>

                    <div className="min-w-0 flex-1 pb-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {vendor.shop_name}
                        </h1>
                        {vendor.description ? (
                            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                                {vendor.description}
                            </p>
                        ) : null}
                    </div>
                </header>

                <div className="grid gap-8 lg:grid-cols-[280px_1fr]">
                    <aside className="space-y-4">
                        <Card>
                            <CardContent className="space-y-3">
                                <div className="flex items-center gap-2 font-medium">
                                    <Truck className="size-4" />
                                    Where this shop delivers
                                </div>

                                {vendor.delivery_notes ? (
                                    <p className="text-xs text-muted-foreground">
                                        {vendor.delivery_notes}
                                    </p>
                                ) : null}

                                <Deferred
                                    data="deliveryAreas"
                                    fallback={
                                        <div className="space-y-2">
                                            {Array.from({ length: 3 }).map(
                                                (_, index) => (
                                                    <Skeleton
                                                        key={index}
                                                        className="h-6 w-full"
                                                    />
                                                ),
                                            )}
                                        </div>
                                    }
                                >
                                    {(deliveryAreas ?? []).length === 0 ? (
                                        <p className="text-xs text-muted-foreground">
                                            This shop has not set up delivery
                                            areas yet.
                                        </p>
                                    ) : (
                                        <div className="overflow-x-auto">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>
                                                            Area
                                                        </TableHead>
                                                        <TableHead className="text-right">
                                                            Fee
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {(deliveryAreas ?? []).map(
                                                        (area) => (
                                                            <TableRow
                                                                key={area.id}
                                                            >
                                                                <TableCell className="text-xs">
                                                                    <span className="font-medium">
                                                                        {
                                                                            area.district
                                                                        }
                                                                    </span>
                                                                    <span className="text-muted-foreground">
                                                                        {area.sector
                                                                            ? ` · ${area.sector}`
                                                                            : ' · all sectors'}
                                                                    </span>
                                                                    <span className="block text-muted-foreground">
                                                                        {formatDeliveryEstimate(
                                                                            area.estimated_days_min,
                                                                            area.estimated_days_max,
                                                                        )}
                                                                    </span>
                                                                </TableCell>
                                                                <TableCell className="text-right text-xs">
                                                                    {area.delivery_fee ===
                                                                    0 ? (
                                                                        'Free'
                                                                    ) : (
                                                                        <Money
                                                                            amount={
                                                                                area.delivery_fee
                                                                            }
                                                                        />
                                                                    )}
                                                                </TableCell>
                                                            </TableRow>
                                                        ),
                                                    )}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    )}
                                </Deferred>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="space-y-1 text-sm">
                                <p className="font-medium">Contact</p>
                                <p className="text-muted-foreground">
                                    {vendor.phone}
                                </p>
                                {vendor.email ? (
                                    <p className="break-all text-muted-foreground">
                                        {vendor.email}
                                    </p>
                                ) : null}
                            </CardContent>
                        </Card>
                    </aside>

                    <div className="space-y-4">
                        {products.data.length === 0 ? (
                            <EmptyState
                                icon={PackageSearch}
                                title="No products yet"
                                description="This shop has not published anything for sale."
                            />
                        ) : (
                            <>
                                <ProductGrid products={products.data} />
                                <PaginationNav paginator={products} />
                            </>
                        )}
                    </div>
                </div>
            </div>
        </StorefrontLayout>
    );
}
