import { Head, router } from '@inertiajs/react';
import { Boxes } from 'lucide-react';
import { useState } from 'react';
import type { DataTableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { ProductStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { SubscriptionBanner } from '@/components/vendor/subscription-banner';
import { VendorNav } from '@/components/vendor/vendor-nav';
import { useTableFilters } from '@/hooks/use-table-filters';
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
 * A product and its variants are separate rows in one table, so the list is
 * flattened to one entry per row before it reaches the table. Rendering a product
 * and its children as a single logical row is what forced the old page to
 * hand-roll its markup.
 */
type InventoryEntry =
    | { kind: 'product'; product: InventoryRow }
    | { kind: 'variant'; product: InventoryRow; variant: InventoryVariant };

/**
 * An inline stock field that only writes when the value actually changed and the
 * field loses focus, so typing "12" does not fire a request for "1" then "12".
 */
function StockField({
    value,
    label,
    onCommit,
}: {
    value: number;
    label: string;
    onCommit: (next: number) => void;
}) {
    const [draft, setDraft] = useState(String(value));

    return (
        <Input
            type="number"
            min={0}
            value={draft}
            aria-label={label}
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
    const { values, set, commit, clear, isFiltered } = useTableFilters({
        url: '/vendor/inventory',
        filters: {
            search: filters.search,
            low_stock: filters.low_stock ? '1' : null,
        },
    });

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

    const entries: InventoryEntry[] = products.data.flatMap((product) => [
        { kind: 'product' as const, product },
        ...product.variants.map((variant) => ({
            kind: 'variant' as const,
            product,
            variant,
        })),
    ]);

    const columns: DataTableColumn<InventoryEntry>[] = [
        {
            id: 'product',
            header: 'Product',
            cell: (entry) =>
                entry.kind === 'product' ? (
                    <>
                        <p className="text-sm font-medium">
                            {entry.product.name}
                        </p>
                        {entry.product.sku ? (
                            <p className="text-xs text-muted-foreground">
                                {entry.product.sku}
                            </p>
                        ) : null}
                    </>
                ) : (
                    <div className="pl-6 text-sm">
                        {/* Indentation is the only visual cue that this row belongs
                            to the product above it, and indentation is invisible to
                            a screen reader. */}
                        <span className="sr-only">
                            Variant of {entry.product.name}:{' '}
                        </span>
                        {entry.variant.name}
                    </div>
                ),
        },
        {
            id: 'status',
            header: 'Status',
            cell: (entry) =>
                entry.kind === 'product' ? (
                    <ProductStatusBadge status={entry.product.status} />
                ) : (
                    <span className="text-xs text-muted-foreground">
                        {entry.variant.is_active ? 'Active' : 'Inactive'}
                    </span>
                ),
        },
        {
            id: 'threshold',
            header: 'Low stock at',
            cellClassName: 'text-sm text-muted-foreground',
            cell: (entry) =>
                entry.kind === 'product'
                    ? entry.product.low_stock_threshold
                    : null,
        },
        {
            id: 'stock',
            header: 'Stock',
            align: 'end',
            cell: (entry) => (
                <div className="flex justify-end">
                    {entry.kind === 'product' ? (
                        <StockField
                            value={entry.product.stock_quantity}
                            label={`Stock for ${entry.product.name}`}
                            onCommit={(next) =>
                                setProductStock(entry.product, next)
                            }
                        />
                    ) : (
                        <StockField
                            value={entry.variant.stock_quantity}
                            label={`Stock for ${entry.product.name}, ${entry.variant.name}`}
                            onCommit={(next) =>
                                setVariantStock(entry.variant, next)
                            }
                        />
                    )}
                </div>
            ),
        },
    ];

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
                            commit();
                        }}
                        className="flex gap-2"
                    >
                        <label htmlFor="inventory-search" className="sr-only">
                            Search products
                        </label>
                        <Input
                            id="inventory-search"
                            type="search"
                            value={values.search ?? ''}
                            onChange={(event) =>
                                set('search', event.target.value)
                            }
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
                            checked={values.low_stock === '1'}
                            onCheckedChange={(checked) =>
                                set('low_stock', checked ? '1' : null, true)
                            }
                        />
                        <Label htmlFor="low_stock">Low stock only</Label>
                    </div>
                </div>

                <DataTable
                    caption="Inventory"
                    columns={columns}
                    rows={entries}
                    getRowKey={(entry) =>
                        entry.kind === 'product'
                            ? `product-${entry.product.id}`
                            : `variant-${entry.variant.id}`
                    }
                    paginator={products}
                    rowClassName={(entry) =>
                        entry.kind === 'variant'
                            ? 'bg-muted/30'
                            : entry.product.is_low_stock
                              ? 'bg-amber-500/5'
                              : undefined
                    }
                    empty={
                        <EmptyState
                            icon={Boxes}
                            title="Nothing to show"
                            description={
                                isFiltered
                                    ? 'No product matches these filters.'
                                    : 'Add products to start tracking stock.'
                            }
                            action={
                                isFiltered ? (
                                    <Button variant="outline" onClick={clear}>
                                        Clear filters
                                    </Button>
                                ) : undefined
                            }
                        />
                    }
                />
            </div>
        </AppLayout>
    );
}
