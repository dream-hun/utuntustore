import { Head, Link, router } from '@inertiajs/react';
import { Ban, Check, Eye, RotateCcw, Store, X } from 'lucide-react';
import { useState } from 'react';
import { AdminNav } from '@/components/admin/admin-nav';
import type { AdminVendorRow } from '@/components/admin/types';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { DataTableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import type { RowAction } from '@/components/row-actions';
import { RowActions } from '@/components/row-actions';
import {
    SubscriptionStatusBadge,
    VendorStatusBadge,
} from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTableFilters } from '@/hooks/use-table-filters';
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
    const [pending, setPending] = useState<{
        vendor: AdminVendorRow;
        action: Action;
    } | null>(null);

    const { values, set, commit, clear, isFiltered } = useTableFilters({
        url: '/admin/vendors',
        filters,
    });

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

    /**
     * Which moderation steps a shop is eligible for depends entirely on where it
     * already sits, so the menu is built from the status rather than rendering
     * every item and disabling most of them.
     */
    const moderationActions = (vendor: AdminVendorRow): RowAction[] => {
        const stage = (action: Action) => () => setPending({ vendor, action });

        if (vendor.status === 'pending') {
            return [
                {
                    label: 'Approve',
                    icon: Check,
                    onSelect: stage('approved'),
                },
                {
                    label: 'Reject',
                    icon: X,
                    destructive: true,
                    onSelect: stage('rejected'),
                },
            ];
        }

        if (vendor.status === 'approved') {
            return [
                {
                    label: 'Suspend',
                    icon: Ban,
                    destructive: true,
                    onSelect: stage('suspended'),
                },
            ];
        }

        return [
            {
                label: 'Reinstate',
                icon: RotateCcw,
                onSelect: stage('approved'),
            },
        ];
    };

    const columns: DataTableColumn<AdminVendorRow>[] = [
        {
            id: 'shop',
            header: 'Shop',
            cell: (vendor) => (
                <>
                    <Link
                        href={`/admin/vendors/${vendor.id}`}
                        className="text-sm font-medium hover:underline"
                    >
                        {vendor.shop_name}
                    </Link>
                    <p className="text-xs text-muted-foreground">
                        {vendor.owner_name} · {vendor.phone}
                    </p>
                </>
            ),
        },
        {
            id: 'status',
            header: 'Status',
            cell: (vendor) => <VendorStatusBadge status={vendor.status} />,
        },
        {
            id: 'subscription',
            header: 'Subscription',
            cell: (vendor) => (
                <SubscriptionStatusBadge status={vendor.subscription_status} />
            ),
        },
        {
            id: 'products',
            header: 'Products',
            cell: (vendor) => (
                <span className="text-sm">{vendor.products_count}</span>
            ),
        },
        {
            id: 'joined',
            header: 'Joined',
            cell: (vendor) => (
                <span className="text-xs text-muted-foreground">
                    {formatDate(vendor.created_at)}
                </span>
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            headerHidden: true,
            headClassName: 'w-10',
            cell: (vendor) => (
                <RowActions
                    rowLabel={vendor.shop_name}
                    groups={[
                        {
                            actions: [
                                {
                                    label: 'View',
                                    icon: Eye,
                                    href: `/admin/vendors/${vendor.id}`,
                                },
                            ],
                        },
                        { actions: moderationActions(vendor) },
                    ]}
                />
            ),
        },
    ];

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
                            commit();
                        }}
                        className="flex gap-2"
                    >
                        <label htmlFor="vendor-search" className="sr-only">
                            Search vendors
                        </label>
                        <Input
                            id="vendor-search"
                            type="search"
                            value={values.search ?? ''}
                            onChange={(event) =>
                                set('search', event.target.value)
                            }
                            placeholder="Shop, owner or phone"
                            className="w-60"
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>

                    <Select
                        value={values.status ?? ANY}
                        onValueChange={(value) =>
                            set('status', value === ANY ? null : value, true)
                        }
                    >
                        <SelectTrigger
                            className="w-44"
                            aria-label="Shop status"
                        >
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
                        value={values.subscription_status ?? ANY}
                        onValueChange={(value) =>
                            set(
                                'subscription_status',
                                value === ANY ? null : value,
                                true,
                            )
                        }
                    >
                        <SelectTrigger
                            className="w-48"
                            aria-label="Subscription status"
                        >
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

                    {isFiltered ? (
                        <Button type="button" variant="ghost" onClick={clear}>
                            Clear filters
                        </Button>
                    ) : null}
                </div>

                <DataTable
                    caption="Vendors"
                    columns={columns}
                    rows={vendors.data}
                    getRowKey={(vendor) => vendor.id}
                    paginator={vendors}
                    empty={
                        <EmptyState
                            icon={Store}
                            title="No vendors found"
                            description={
                                isFiltered
                                    ? 'No shop matches these filters.'
                                    : 'Shops appear here once someone applies to sell on the marketplace.'
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
