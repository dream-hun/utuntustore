import { Head, router } from '@inertiajs/react';
import { FolderTree, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminNav } from '@/components/admin/admin-nav';
import { CategoryFormModal } from '@/components/admin/category-form-modal';
import type {
    AdminCategoryRow,
    CategoryParentOption,
} from '@/components/admin/types';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { DataTableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { RowActions } from '@/components/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTableFilters } from '@/hooks/use-table-filters';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types/marketplace';

export default function AdminCategories({
    categories,
    filters,
    parents,
}: {
    categories: Paginated<AdminCategoryRow>;
    filters: { search: string | null };
    parents: CategoryParentOption[];
}) {
    const [editing, setEditing] = useState<AdminCategoryRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<AdminCategoryRow | null>(null);

    const { values, set, commit, clear, isFiltered } = useTableFilters({
        url: '/admin/categories',
        filters,
    });

    const columns: DataTableColumn<AdminCategoryRow>[] = [
        {
            id: 'name',
            header: 'Name',
            cell: (category) => (
                <>
                    <p className="text-sm font-medium">{category.name}</p>
                    <p className="text-xs text-muted-foreground">
                        {category.slug}
                    </p>
                </>
            ),
        },
        {
            id: 'parent',
            header: 'Parent',
            cell: (category) => (
                <span className="text-sm text-muted-foreground">
                    {category.parent?.name ?? '—'}
                </span>
            ),
        },
        {
            id: 'products',
            header: 'Products',
            cell: (category) => (
                <span className="text-sm">{category.products_count}</span>
            ),
        },
        {
            id: 'active',
            header: 'Active',
            cell: (category) =>
                category.is_active ? (
                    <Badge className="border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                        Active
                    </Badge>
                ) : (
                    <Badge variant="secondary">Hidden</Badge>
                ),
        },
        {
            id: 'actions',
            header: 'Actions',
            headerHidden: true,
            headClassName: 'w-10',
            cell: (category) => (
                <RowActions
                    rowLabel={category.name}
                    groups={[
                        {
                            actions: [
                                {
                                    label: 'Edit',
                                    icon: Pencil,
                                    onSelect: () => setEditing(category),
                                },
                            ],
                        },
                        {
                            actions: [
                                {
                                    label: 'Delete',
                                    icon: Trash2,
                                    destructive: true,
                                    disabled:
                                        category.products_count > 0 ||
                                        category.children_count > 0,
                                    onSelect: () => setDeleting(category),
                                },
                            ],
                        },
                    ]}
                />
            ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Categories', href: '/admin/categories' },
            ]}
        >
            <Head title="Categories" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Categories
                    </h1>
                    <Button onClick={() => setCreating(true)}>
                        <Plus className="size-4" />
                        New category
                    </Button>
                </div>

                <AdminNav />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        commit();
                    }}
                    className="flex gap-2"
                >
                    <label htmlFor="category-search" className="sr-only">
                        Search categories
                    </label>
                    <Input
                        id="category-search"
                        type="search"
                        value={values.search ?? ''}
                        onChange={(event) => set('search', event.target.value)}
                        placeholder="Search categories"
                        className="w-60"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                    {isFiltered ? (
                        <Button type="button" variant="ghost" onClick={clear}>
                            Clear
                        </Button>
                    ) : null}
                </form>

                <DataTable
                    caption="Categories"
                    columns={columns}
                    rows={categories.data}
                    getRowKey={(category) => category.id}
                    paginator={categories}
                    empty={
                        <EmptyState
                            icon={FolderTree}
                            title={
                                isFiltered
                                    ? 'No categories found'
                                    : 'No categories yet'
                            }
                            description={
                                isFiltered
                                    ? 'No category matches that search.'
                                    : 'Categories organise the storefront. Vendors pick one for each product.'
                            }
                            action={
                                isFiltered ? (
                                    <Button variant="outline" onClick={clear}>
                                        Clear search
                                    </Button>
                                ) : (
                                    <Button onClick={() => setCreating(true)}>
                                        <Plus className="size-4" />
                                        New category
                                    </Button>
                                )
                            }
                        />
                    }
                />
            </div>

            <CategoryFormModal
                open={creating || editing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCreating(false);
                        setEditing(null);
                    }
                }}
                category={editing}
                parents={parents}
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="Delete this category?"
                description={`"${deleting?.name}" will be removed. A category that still has products or child categories cannot be deleted.`}
                confirmLabel="Delete"
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    router.delete(`/admin/categories/${deleting.id}`, {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    });
                }}
            />
        </AppLayout>
    );
}
