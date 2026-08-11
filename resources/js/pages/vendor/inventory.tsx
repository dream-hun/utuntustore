import { Head, router } from '@inertiajs/react';
import { Boxes } from 'lucide-react';
import { Fragment, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PaginationNav } from '@/components/pagination-nav';
import { ProductStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { SubscriptionBanner } from '@/components/vendor/subscription-banner';
import { VendorNav } from '@/components/vendor/vendor-nav';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, ProductStatus } from '@/types/marketplace';

interface InventoryVariant {
    id: string;
    name: string;
    sku: string | null;
    price: number;
    stock_quantity: number;
    is_active: boolean;
}

interface InventoryRow {
    id: string;
    name: string;
    sku: string | null;
    status: ProductStatus;
    stock_quantity: number;
    low_stock_threshold: number;
    is_low_stock: boolean;
    variants: InventoryVariant[];
}

/**
 * An inline stock field that only writes when the value actually changed and the
 * field loses focus, so typing "12" does not fire a request for "1" then "12".
 */
function StockField({
    value,
    onCommit,
}: {
    value: number;
    onCommit: (next: number) => void;
}) {
    const [draft, setDraft] = useState(String(value));

    return (
        <Input
            type="number"
            min={0}
            value={draft}
            className="h-8 w-24"
            onChange={(event) => setDraft(event.target.value)}
            onBlur={() => {
                const next = Number(draft);

                if (!Number.isNaN(next) && next !== value) {
                    onCommit(next);
                }
            }}
        />
    );
}

export default function VendorInventory({
    products,
    filters,
}: {
    products: Paginated<InventoryRow>;
    filters: { search: string | null; low_stock: boolean };
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    const apply = (next: Record<string, string | undefined>) => {
        router.get(
            '/vendor/inventory',
            {
                search: search || undefined,
                low_stock: filters.low_stock ? '1' : undefined,
                ...next,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const setProductStock = (product: InventoryRow, stock: number) => {
        router.put(
            `/vendor/inventory/products/${product.id}`,
            { stock_quantity: stock },
            { preserveScroll: true },
        );
    };

    const setVariantStock = (variant: InventoryVariant, stock: number) => {
        router.put(
            `/vendor/inventory/variants/${variant.id}`,
            { stock_quantity: stock },
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Vendor', href: '/vendor' },
                { title: 'Inventory', href: '/vendor/inventory' },
            ]}
        >
            <Head title="Inventory" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Inventory
                </h1>

                <SubscriptionBanner />
                <VendorNav />

                <div className="flex flex-wrap items-center gap-4">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply({});
                        }}
                        className="flex gap-2"
                    >
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search products"
                            className="w-56"
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>

                    <div className="flex items-center gap-2">
                        <Switch
                            id="low_stock"
                            checked={filters.low_stock}
                            onCheckedChange={(checked) =>
                                apply({ low_stock: checked ? '1' : undefined })
                            }
                        />
                        <Label htmlFor="low_stock">Low stock only</Label>
                    </div>
                </div>

                {products.data.length === 0 ? (
                    <EmptyState
                        icon={Boxes}
                        title="Nothing to show"
                        description="Add products to start tracking stock."
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Low stock at</TableHead>
                                        <TableHead className="text-right">
                                            Stock
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {products.data.map((product) => (
                                        <Fragment key={product.id}>
                                            <TableRow
                                                className={
                                                    product.is_low_stock
                                                        ? 'bg-amber-500/5'
                                                        : undefined
                                                }
                                            >
                                                <TableCell>
                                                    <p className="text-sm font-medium">
                                                        {product.name}
                                                    </p>
                                                    {product.sku ? (
                                                        <p className="text-xs text-muted-foreground">
                                                            {product.sku}
                                                        </p>
                                                    ) : null}
                                                </TableCell>
                                                <TableCell>
                                                    <ProductStatusBadge
                                                        status={product.status}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-sm text-muted-foreground">
                                                    {
                                                        product.low_stock_threshold
                                                    }
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex justify-end">
                                                        <StockField
                                                            value={
                                                                product.stock_quantity
                                                            }
                                                            onCommit={(next) =>
                                                                setProductStock(
                                                                    product,
                                                                    next,
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                </TableCell>
                                            </TableRow>

                                            {product.variants.map((variant) => (
                                                <TableRow
                                                    key={variant.id}
                                                    className="bg-muted/30"
                                                >
                                                    <TableCell className="pl-8 text-sm">
                                                        {variant.name}
                                                    </TableCell>
                                                    <TableCell className="text-xs text-muted-foreground">
                                                        {variant.is_active
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </TableCell>
                                                    <TableCell />
                                                    <TableCell>
                                                        <div className="flex justify-end">
                                                            <StockField
                                                                value={
                                                                    variant.stock_quantity
                                                                }
                                                                onCommit={(
                                                                    next,
                                                                ) =>
                                                                    setVariantStock(
                                                                        variant,
                                                                        next,
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </Fragment>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <PaginationNav paginator={products} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
