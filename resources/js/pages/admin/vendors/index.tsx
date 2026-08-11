import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal, Store } from 'lucide-react';
import { useState } from 'react';
import { AdminNav } from '@/components/admin/admin-nav';
import type { AdminVendorRow } from '@/components/admin/types';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PaginationNav } from '@/components/pagination-nav';
import {
    SubscriptionStatusBadge,
    VendorStatusBadge,
} from '@/components/status-badge';
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
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type { Paginated } from '@/types/marketplace';

const ANY = 'any';

/** The moderation endpoint accepts the target status directly. */
type Action = 'approved' | 'rejected' | 'suspended';

const actionCopy: Record<
    Action,
    { title: string; description: string; label: string }
> = {
    approved: {
        title: 'Approve this shop?',
        label: 'Approve',
        description:
            'The vendor can set up their catalog. They still need an active subscription before anything appears on the storefront.',
    },
    rejected: {
        title: 'Reject this application?',
        label: 'Reject',
        description: 'The vendor will not be able to sell on the marketplace.',
    },
    suspended: {
        title: 'Suspend this shop?',
        label: 'Suspend',
        description:
            'Their listings disappear from the storefront immediately. Nothing is deleted, and suspension is the platform’s only recourse in a dispute — it never held the customer’s money.',
    },
};

export default function AdminVendors({
    vendors,
    filters,
}: {
    vendors: Paginated<AdminVendorRow>;
    filters: {
        search: string | null;
        status: string | null;
        subscription_status: string | null;
    };
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [pending, setPending] = useState<{
        vendor: AdminVendorRow;
        action: Action;
    } | null>(null);

    const apply = (next: Record<string, string | undefined>) => {
        router.get(
            '/admin/vendors',
            {
                search: search || undefined,
                status: filters.status ?? undefined,
                subscription_status: filters.subscription_status ?? undefined,
                ...next,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const moderate = () => {
        if (!pending) {
            return;
        }

        router.patch(
            `/admin/vendors/${pending.vendor.id}/moderate`,
            { status: pending.action },
            { preserveScroll: true, onFinish: () => setPending(null) },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Vendors', href: '/admin/vendors' },
            ]}
        >
            <Head title="Vendors" />

            <div className="flex flex-col gap-6 p-4">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Vendors
                </h1>

                <AdminNav />

                <div className="flex flex-wrap gap-3">
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
                            placeholder="Shop, owner or phone"
                            className="w-60"
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>

                    <Select
                        value={filters.status ?? ANY}
                        onValueChange={(value) =>
                            apply({ status: value === ANY ? undefined : value })
                        }
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="Any status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any status</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="approved">Approved</SelectItem>
                            <SelectItem value="rejected">Rejected</SelectItem>
                            <SelectItem value="suspended">Suspended</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.subscription_status ?? ANY}
                        onValueChange={(value) =>
                            apply({
                                subscription_status:
                                    value === ANY ? undefined : value,
                            })
                        }
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="Any subscription" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>
                                Any subscription
                            </SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="grace">Grace</SelectItem>
                            <SelectItem value="expired">Expired</SelectItem>
                            <SelectItem value="none">None</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {vendors.data.length === 0 ? (
                    <EmptyState
                        icon={Store}
                        title="No vendors found"
                        description="Try a different filter."
                    />
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Shop</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Subscription</TableHead>
                                        <TableHead>Products</TableHead>
                                        <TableHead>Joined</TableHead>
                                        <TableHead className="w-10" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {vendors.data.map((vendor) => (
                                        <TableRow key={vendor.id}>
                                            <TableCell>
                                                <Link
                                                    href={`/admin/vendors/${vendor.id}`}
                                                    className="text-sm font-medium hover:underline"
                                                >
                                                    {vendor.shop_name}
                                                </Link>
                                                <p className="text-xs text-muted-foreground">
                                                    {vendor.owner_name} ·{' '}
                                                    {vendor.phone}
                                                </p>
                                            </TableCell>
                                            <TableCell>
                                                <VendorStatusBadge
                                                    status={vendor.status}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <SubscriptionStatusBadge
                                                    status={
                                                        vendor.subscription_status
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {vendor.products_count}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {formatDate(vendor.created_at)}
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
                                                            asChild
                                                        >
                                                            <Link
                                                                href={`/admin/vendors/${vendor.id}`}
                                                            >
                                                                View
                                                            </Link>
                                                        </DropdownMenuItem>

                                                        {vendor.status ===
                                                        'pending' ? (
                                                            <>
                                                                <DropdownMenuSeparator />
                                                                <DropdownMenuItem
                                                                    onSelect={() =>
                                                                        setPending(
                                                                            {
                                                                                vendor,
                                                                                action: 'approved',
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    Approve
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem
                                                                    variant="destructive"
                                                                    onSelect={() =>
                                                                        setPending(
                                                                            {
                                                                                vendor,
                                                                                action: 'rejected',
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    Reject
                                                                </DropdownMenuItem>
                                                            </>
                                                        ) : null}

                                                        {vendor.status ===
                                                        'approved' ? (
                                                            <>
                                                                <DropdownMenuSeparator />
                                                                <DropdownMenuItem
                                                                    variant="destructive"
                                                                    onSelect={() =>
                                                                        setPending(
                                                                            {
                                                                                vendor,
                                                                                action: 'suspended',
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    Suspend
                                                                </DropdownMenuItem>
                                                            </>
                                                        ) : null}

                                                        {vendor.status ===
                                                            'suspended' ||
                                                        vendor.status ===
                                                            'rejected' ? (
                                                            <>
                                                                <DropdownMenuSeparator />
                                                                <DropdownMenuItem
                                                                    onSelect={() =>
                                                                        setPending(
                                                                            {
                                                                                vendor,
                                                                                action: 'approved',
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    Reinstate
                                                                </DropdownMenuItem>
                                                            </>
                                                        ) : null}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <PaginationNav paginator={vendors} />
                    </>
                )}
            </div>

            <ConfirmDialog
                open={pending !== null}
                onOpenChange={(open) => !open && setPending(null)}
                title={pending ? actionCopy[pending.action].title : ''}
                description={
                    pending ? actionCopy[pending.action].description : ''
                }
                confirmLabel={
                    pending ? actionCopy[pending.action].label : 'Confirm'
                }
                destructive={
                    pending?.action === 'rejected' ||
                    pending?.action === 'suspended'
                }
                onConfirm={moderate}
            />
        </AppLayout>
    );
}
