import { Head, router } from '@inertiajs/react';
import { FolderTree, MoreHorizontal, Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminNav } from '@/components/admin/admin-nav';
import { CategoryFormModal } from '@/components/admin/category-form-modal';
import type {
    AdminCategoryRow,
    CategoryParentOption,
} from '@/components/admin/types';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PaginationNav } from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<AdminCategoryRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<AdminCategoryRow | null>(null);

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
                        router.get(
                            '/admin/categories',
                            { search: search || undefined },
                            {
                                preserveState: true,
                                preserveScroll: true,
                                replace: true,
                            },
                        );
                    }}
                    className="flex gap-2"
                >
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search categories"
                        className="w-60"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                {categories.data.length === 0 ? (
                    <EmptyState
                        icon={FolderTree}
                        title="No categories yet"
                        description="Categories organise the storefront. Vendors pick one for each product."
                        action={
                            <Button onClick={() => setCreating(true)}>
                                <Plus className="size-4" />
                                New category
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Parent</TableHead>
                                        <TableHead>Products</TableHead>
                                        <TableHead>Active</TableHead>
                                        <TableHead className="w-10" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {categories.data.map((category) => (
                                        <TableRow key={category.id}>
                                            <TableCell>
                                                <p className="text-sm font-medium">
                                                    {category.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {category.slug}
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-sm text-muted-foreground">
                                                {category.parent?.name ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {category.products_count}
                                            </TableCell>
                                            <TableCell>
                                                {category.is_active ? (
                                                    <Badge className="border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                                                        Active
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        Hidden
                                                    </Badge>
                                                )}
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
                                                                setEditing(
                                                                    category,
                                                                )
                                                            }
                                                        >
                                                            Edit
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            disabled={
                                                                category.products_count >
                                                                    0 ||
                                                                category.children_count >
                                                                    0
                                                            }
                                                            onSelect={() =>
                                                                setDeleting(
                                                                    category,
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

                        <PaginationNav paginator={categories} />
                    </>
                )}
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
