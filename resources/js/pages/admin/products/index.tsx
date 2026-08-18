import { Deferred, Head, router } from '@inertiajs/react';
import { Package, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ProductFormModal } from '@/components/admin/product-form-modal';
import type {
    AdminProductRow,
    AdminVendorOption,
} from '@/components/admin/types';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { DataTableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { Image } from '@/components/image';
import { Money } from '@/components/money';
import { RowActions } from '@/components/row-actions';
import { ProductStatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { useTableFilters } from '@/hooks/use-table-filters';
import admin from '@/routes/admin';
import type { Paginated } from '@/types/marketplace';

/** The selects need a non-empty value for "no filter". */
const ANY = 'any';

/**
 * Every shop's catalog in one list, and the screen where the platform curates it. A
 * product always belongs to a vendor, so adding one starts by choosing the shop; an
 * edit cannot change it afterwards.
 *
 * Publishing an existing product is deliberately absent: it puts stock in front of a
 * buyer and answers to that shop's own selling eligibility, so it stays with the vendor.
 */
export default function AdminProducts({
    products,
    filters,
    categories,
    vendors,
}: {
    products: Paginated<AdminProductRow>;
    filters: {
        search: string | null;
        status: string | null;
        category: string | null;
        vendor: string | null;
    };
    categories?: { id: string; name: string }[];
    vendors?: AdminVendorOption[];
}) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<AdminProductRow | null>(null);
    const [deleting, setDeleting] = useState<AdminProductRow | null>(null);

    const { values, set, commit, clear, isFiltered } = useTableFilters({
        url: '/admin/products',
        filters,
    });

    const columns: DataTableColumn<AdminProductRow>[] = [
        {
            id: 'product',
            header: 'Product',
            cell: (product) => (
                <div className="flex items-center gap-3">
                    <div className="size-10 shrink-0 overflow-hidden rounded-md bg-muted">
                        <Image
                            src={product.images[0]?.thumb_url}
                            alt=""
                            iconClassName="size-4"
                            className="size-full object-cover"
                        />
                    </div>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                            {product.name}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {product.category.name}
                        </p>
                    </div>
                </div>
            ),
        },
        {
            id: 'vendor',
            header: 'Shop',
            cell: (product) => (
                <span className="text-sm text-muted-foreground">
                    {product.vendor.shop_name}
                </span>
            ),
        },
        {
            id: 'status',
            header: 'Status',
            cell: (product) => <ProductStatusBadge status={product.status} />,
        },
        {
            id: 'stock',
            header: 'Stock',
            cell: (product) => (
                <span
                    className={
                        product.is_low_stock
                            ? 'text-sm font-medium text-amber-600 dark:text-amber-400'
                            : 'text-sm'
                    }
                >
                    {product.stock_quantity}
                    {product.is_low_stock ? (
                        <span className="sr-only"> — low stock</span>
                    ) : null}
                </span>
            ),
        },
        {
            id: 'price',
            header: 'Price',
            align: 'end',
            cell: (product) => (
                <Money amount={product.price} currency={product.currency} />
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            headerHidden: true,
            headClassName: 'w-10',
            cell: (product) => (
                <RowActions
                    rowLabel={product.name}
                    groups={[
                        {
                            actions: [
                                {
                                    label: 'Edit',
                                    icon: Pencil,
                                    onSelect: () => setEditing(product),
                                },
                            ],
                        },
                        {
                            actions: [
                                {
                                    label: 'Delete',
                                    icon: Trash2,
                                    destructive: true,
                                    onSelect: () => setDeleting(product),
                                },
                            ],
                        },
                    ]}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Products" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Products
                    </h1>
                    <Button onClick={() => setCreating(true)}>
                        <Plus className="size-4" />
                        New product
                    </Button>
                </div>

                <div className="flex flex-wrap gap-3">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            commit();
                        }}
                        className="flex gap-2"
                    >
                        <label htmlFor="product-search" className="sr-only">
                            Search products
                        </label>
                        <Input
                            id="product-search"
                            type="search"
                            value={values.search ?? ''}
                            onChange={(event) =>
                                set('search', event.target.value)
                            }
                            placeholder="Search name or SKU"
                            className="w-56"
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>

                    <Deferred
                        data="vendors"
                        fallback={<Skeleton className="h-9 w-44" />}
                    >
                        <Select
                            value={values.vendor ?? ANY}
                            onValueChange={(value) =>
                                set(
                                    'vendor',
                                    value === ANY ? null : value,
                                    true,
                                )
                            }
                        >
                            <SelectTrigger className="w-44" aria-label="Shop">
                                <SelectValue placeholder="Any shop" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>Any shop</SelectItem>
                                {(vendors ?? []).map((vendor) => (
                                    <SelectItem
                                        key={vendor.id}
                                        value={vendor.id}
                                    >
                                        {vendor.shop_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Deferred>

                    <Deferred
                        data="categories"
                        fallback={<Skeleton className="h-9 w-44" />}
                    >
                        <Select
                            value={values.category ?? ANY}
                            onValueChange={(value) =>
                                set(
                                    'category',
                                    value === ANY ? null : value,
                                    true,
                                )
                            }
                        >
                            <SelectTrigger
                                className="w-44"
                                aria-label="Category"
                            >
                                <SelectValue placeholder="Any category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ANY}>
                                    Any category
                                </SelectItem>
                                {(categories ?? []).map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Deferred>

                    <Select
                        value={values.status ?? ANY}
                        onValueChange={(value) =>
                            set('status', value === ANY ? null : value, true)
                        }
                    >
                        <SelectTrigger className="w-40" aria-label="Status">
                            <SelectValue placeholder="Any status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any status</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="published">Published</SelectItem>
                            <SelectItem value="archived">Archived</SelectItem>
                        </SelectContent>
                    </Select>

                    {isFiltered ? (
                        <Button type="button" variant="ghost" onClick={clear}>
                            Clear filters
                        </Button>
                    ) : null}
                </div>

                <DataTable
                    caption="Products"
                    columns={columns}
                    rows={products.data}
                    getRowKey={(product) => product.id}
                    paginator={products}
                    empty={
                        <EmptyState
                            icon={Package}
                            title={
                                isFiltered
                                    ? 'No products found'
                                    : 'No products yet'
                            }
                            description={
                                isFiltered
                                    ? 'No product matches these filters.'
                                    : 'Vendors add their own products. You can also add one to an approved shop yourself.'
                            }
                            action={
                                isFiltered ? (
                                    <Button variant="outline" onClick={clear}>
                                        Clear filters
                                    </Button>
                                ) : (
                                    <Button onClick={() => setCreating(true)}>
                                        <Plus className="size-4" />
                                        New product
                                    </Button>
                                )
                            }
                        />
                    }
                />
            </div>

            {creating || editing !== null ? (
                // Remounted per row so the form is seeded from the product being edited.
                <ProductFormModal
                    key={editing?.id ?? 'new'}
                    open
                    onOpenChange={(open) => {
                        if (!open) {
                            setCreating(false);
                            setEditing(null);
                        }
                    }}
                    product={editing}
                    vendors={vendors}
                    categories={categories}
                />
            ) : null}

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="Delete this product?"
                description={`"${deleting?.name}" will be removed from ${deleting?.vendor.shop_name}. One that has already been ordered is archived instead, so past orders keep their record of it.`}
                confirmLabel="Delete"
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(admin.products.destroy.url(deleting.id), {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </>
    );
}

AdminProducts.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Products', href: '/admin/products' },
    ],
};
