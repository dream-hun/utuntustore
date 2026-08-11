import { Deferred, Head, router, useForm, usePage } from '@inertiajs/react';
import { ImageOff, MoreHorizontal, Package, Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormModal } from '@/components/form-modal';
import InputError from '@/components/input-error';
import { Money } from '@/components/money';
import { PaginationNav } from '@/components/pagination-nav';
import { ProductStatusBadge } from '@/components/status-badge';
import { SubscriptionBanner } from '@/components/vendor/subscription-banner';
import { VendorNav } from '@/components/vendor/vendor-nav';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, ProductStatus } from '@/types/marketplace';

interface ProductRow {
    id: string;
    name: string;
    slug: string;
    sku: string | null;
    description: string | null;
    short_description: string | null;
    price: number;
    compare_at_price: number | null;
    currency: string;
    stock_quantity: number;
    low_stock_threshold: number;
    weight: number | null;
    status: ProductStatus;
    published_at: string | null;
    is_low_stock: boolean;
    variants_count: number;
    category: { id: string; name: string };
    images: { id: string; url: string; thumb_url: string }[];
}

const ANY = 'any';

export default function VendorProducts({
    products,
    filters,
    categories,
}: {
    products: Paginated<ProductRow>;
    filters: {
        search: string | null;
        status: string | null;
        category: string | null;
    };
    categories?: { id: string; name: string }[];
}) {
    const canSell = Boolean(
        (usePage().props.auth as { vendor?: { can_sell: boolean } })?.vendor
            ?.can_sell,
    );

    const [editing, setEditing] = useState<ProductRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<ProductRow | null>(null);
    const [search, setSearch] = useState(filters.search ?? '');

    const form = useForm<{
        name: string;
        category_id: string;
        description: string;
        short_description: string;
        sku: string;
        price: number;
        compare_at_price: string;
        stock_quantity: number;
        low_stock_threshold: number;
        weight: string;
        images: File[];
    }>({
        name: '',
        category_id: '',
        description: '',
        short_description: '',
        sku: '',
        price: 0,
        compare_at_price: '',
        stock_quantity: 0,
        low_stock_threshold: 5,
        weight: '',
        images: [],
    });

    const openCreate = () => {
        form.reset();
        form.clearErrors();
        setCreating(true);
    };

    const openEdit = (product: ProductRow) => {
        form.setData({
            name: product.name,
            category_id: product.category.id,
            description: product.description ?? '',
            short_description: product.short_description ?? '',
            sku: product.sku ?? '',
            price: product.price,
            compare_at_price: product.compare_at_price?.toString() ?? '',
            stock_quantity: product.stock_quantity,
            low_stock_threshold: product.low_stock_threshold,
            weight: product.weight?.toString() ?? '',
            images: [],
        });
        form.clearErrors();
        setEditing(product);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setCreating(false);
                setEditing(null);
                form.reset();
            },
        };

        if (editing) {
            // POST, not PUT: the body is multipart because it can carry images.
            form.post(`/vendor/products/${editing.id}`, options);

            return;
        }

        form.post('/vendor/products', options);
    };

    const applyFilters = (next: Record<string, string | undefined>) => {
        router.get(
            '/vendor/products',
            {
                search: search || undefined,
                status: filters.status ?? undefined,
                category: filters.category ?? undefined,
                ...next,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const togglePublication = (product: ProductRow) => {
        if (product.status === 'published') {
            router.delete(`/vendor/products/${product.id}/publish`, {
                preserveScroll: true,
            });

            return;
        }

        router.post(
            `/vendor/products/${product.id}/publish`,
            {},
            { preserveScroll: true },
        );
    };

    const fields = (
        <>
            <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label>Category</Label>
                <Deferred
                    data="categories"
                    fallback={<Skeleton className="h-9 w-full" />}
                >
                    <Select
                        value={form.data.category_id}
                        onValueChange={(value) =>
                            form.setData('category_id', value)
                        }
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Choose a category" />
                        </SelectTrigger>
                        <SelectContent>
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
                <InputError message={form.errors.category_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="short_description">Short description</Label>
                <Input
                    id="short_description"
                    value={form.data.short_description}
                    onChange={(event) =>
                        form.setData('short_description', event.target.value)
                    }
                />
                <InputError message={form.errors.short_description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                    id="description"
                    rows={4}
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="price">Price (FRW)</Label>
                    <Input
                        id="price"
                        type="number"
                        min={0}
                        value={form.data.price}
                        onChange={(event) =>
                            form.setData('price', Number(event.target.value))
                        }
                    />
                    <InputError message={form.errors.price} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="compare_at_price">
                        Compare-at price (FRW)
                    </Label>
                    <Input
                        id="compare_at_price"
                        type="number"
                        min={0}
                        value={form.data.compare_at_price}
                        onChange={(event) =>
                            form.setData('compare_at_price', event.target.value)
                        }
                    />
                    <InputError message={form.errors.compare_at_price} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="stock_quantity">Stock</Label>
                    <Input
                        id="stock_quantity"
                        type="number"
                        min={0}
                        value={form.data.stock_quantity}
                        onChange={(event) =>
                            form.setData(
                                'stock_quantity',
                                Number(event.target.value),
                            )
                        }
                    />
                    <InputError message={form.errors.stock_quantity} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="low_stock_threshold">Low stock at</Label>
                    <Input
                        id="low_stock_threshold"
                        type="number"
                        min={0}
                        value={form.data.low_stock_threshold}
                        onChange={(event) =>
                            form.setData(
                                'low_stock_threshold',
                                Number(event.target.value),
                            )
                        }
                    />
                    <InputError message={form.errors.low_stock_threshold} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="sku">SKU</Label>
                    <Input
                        id="sku"
                        value={form.data.sku}
                        onChange={(event) =>
                            form.setData('sku', event.target.value)
                        }
                    />
                    <InputError message={form.errors.sku} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="images">Images</Label>
                <Input
                    id="images"
                    type="file"
                    accept="image/*"
                    multiple
                    onChange={(event) =>
                        form.setData(
                            'images',
                            Array.from(event.target.files ?? []),
                        )
                    }
                />
                <InputError message={form.errors.images} />
            </div>
        </>
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Vendor', href: '/vendor' },
                { title: 'Products', href: '/vendor/products' },
            ]}
        >
            <Head title="Products" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Products
                    </h1>
                    <Button onClick={openCreate} disabled={!canSell}>
                        <Plus className="size-4" />
                        New product
                    </Button>
                </div>

                <SubscriptionBanner />
                <VendorNav />

                <div className="flex flex-wrap gap-3">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilters({});
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

                    <Select
                        value={filters.status ?? ANY}
                        onValueChange={(value) =>
                            applyFilters({
                                status: value === ANY ? undefined : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Any status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any status</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="published">Published</SelectItem>
                            <SelectItem value="archived">Archived</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {products.data.length === 0 ? (
                    <EmptyState
                        icon={Package}
                        title="No products yet"
                        description="Add your first product, then publish it so customers can buy it."
                        action={
                            <Button onClick={openCreate} disabled={!canSell}>
                                <Plus className="size-4" />
                                New product
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Product</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Stock</TableHead>
                                        <TableHead className="text-right">
                                            Price
                                        </TableHead>
                                        <TableHead className="w-10" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {products.data.map((product) => (
                                        <TableRow key={product.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div className="size-10 shrink-0 overflow-hidden rounded-md bg-muted">
                                                        {product.images[0] ? (
                                                            <img
                                                                src={
                                                                    product
                                                                        .images[0]
                                                                        .thumb_url
                                                                }
                                                                alt={
                                                                    product.name
                                                                }
                                                                className="size-full object-cover"
                                                            />
                                                        ) : (
                                                            <div className="flex size-full items-center justify-center text-muted-foreground">
                                                                <ImageOff className="size-4" />
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">
                                                            {product.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                product.category
                                                                    .name
                                                            }
                                                        </p>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <ProductStatusBadge
                                                    status={product.status}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <span
                                                    className={
                                                        product.is_low_stock
                                                            ? 'text-sm font-medium text-amber-600 dark:text-amber-400'
                                                            : 'text-sm'
                                                    }
                                                >
                                                    {product.stock_quantity}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Money
                                                    amount={product.price}
                                                    currency={product.currency}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                        >
                                                            <MoreHorizontal className="size-4" />
                                                            <span className="sr-only">
                                                                Actions
                                                            </span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            onSelect={() =>
                                                                openEdit(
                                                                    product,
                                                                )
                                                            }
                                                        >
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            disabled={
                                                                !canSell &&
                                                                product.status !==
                                                                    'published'
                                                            }
                                                            onSelect={() =>
                                                                togglePublication(
                                                                    product,
                                                                )
                                                            }
                                                        >
                                                            {product.status ===
                                                            'published'
                                                                ? 'Unpublish'
                                                                : 'Publish'}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onSelect={() =>
                                                                setDeleting(
                                                                    product,
                                                                )
                                                            }
                                                        >
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <PaginationNav paginator={products} />
                    </>
                )}
            </div>

            <FormModal
                open={creating || editing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCreating(false);
                        setEditing(null);
                    }
                }}
                title={editing ? 'Edit product' : 'New product'}
                onSubmit={submit}
                processing={form.processing}
                submitLabel={editing ? 'Save changes' : 'Create product'}
                size="lg"
            >
                {fields}
            </FormModal>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="Delete this product?"
                description={`"${deleting?.name}" will be removed from your catalog. Past orders keep their own record of it.`}
                confirmLabel="Delete"
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(`/vendor/products/${deleting.id}`, {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </AppLayout>
    );
}
