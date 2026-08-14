import { Deferred, Head, Link, router } from '@inertiajs/react';
import { Banknote, CircleSlash, Plus } from 'lucide-react';
import { useState } from 'react';
import { AdminNav } from '@/components/admin/admin-nav';
import { RecordPaymentModal } from '@/components/admin/record-payment-modal';
import {
    ListSkeleton,
    StatCard,
    StatCardSkeleton,
} from '@/components/admin/stat-card';
import type {
    AdminSubscriptionRow,
    ExpiringVendor,
    PayableVendor,
    RevenueTotals,
} from '@/components/admin/types';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { DataTableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { EmptyState } from '@/components/empty-state';
import { Money } from '@/components/money';
import { RowActions } from '@/components/row-actions';
import { SubscriptionStatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTableFilters } from '@/hooks/use-table-filters';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatRelativeDays } from '@/lib/format';
import type { Paginated } from '@/types/marketplace';

const ANY = 'any';

/**
 * These values are the ones `SubscriptionController::period()` matches on. The
 * select previously offered `month` and `year`, which that match statement does
 * not recognise, so both fell through to its `all` default and the filter
 * silently did nothing.
 */
const ALL_TIME = 'all';

const periodOptions = [
    { value: ALL_TIME, label: 'All time' },
    { value: 'this_month', label: 'This month' },
    { value: 'last_month', label: 'Last month' },
    { value: 'this_year', label: 'This year' },
    { value: 'last_12_months', label: 'Last 12 months' },
];

export default function AdminSubscriptions({
    subscriptions,
    revenue,
    upcomingExpiries,
    vendors,
    fee,
    filters,
}: {
    subscriptions: Paginated<AdminSubscriptionRow>;
    revenue?: {
        period: RevenueTotals;
        all_time: RevenueTotals;
        matching_filter: RevenueTotals;
    };
    upcomingExpiries?: ExpiringVendor[];
    vendors: PayableVendor[];
    fee: { amount: number; currency: string; days: number };
    filters: { status: string | null; period: string | null };
}) {
    const [recording, setRecording] = useState(false);
    const [cancelling, setCancelling] = useState<AdminSubscriptionRow | null>(
        null,
    );

    const { values, set } = useTableFilters({
        url: '/admin/subscriptions',
        filters,
    });

    const columns: DataTableColumn<AdminSubscriptionRow>[] = [
        {
            id: 'shop',
            header: 'Shop',
            cell: (subscription) => (
                <Link
                    href={`/admin/vendors/${subscription.vendor.id}`}
                    className="text-sm font-medium hover:underline"
                >
                    {subscription.vendor.shop_name}
                </Link>
            ),
        },
        {
            id: 'period',
            header: 'Period',
            cellClassName: 'text-xs whitespace-nowrap',
            cell: (subscription) => (
                <>
                    {formatDate(subscription.starts_at)} –{' '}
                    {formatDate(subscription.ends_at)}
                </>
            ),
        },
        {
            id: 'status',
            header: 'Status',
            cellClassName: 'text-xs capitalize',
            cell: (subscription) => subscription.status,
        },
        {
            id: 'reference',
            header: 'Reference',
            cellClassName: 'text-xs',
            cell: (subscription) => subscription.reference ?? '—',
        },
        {
            id: 'amount',
            header: 'Amount',
            align: 'end',
            cell: (subscription) => (
                <Money
                    amount={subscription.amount}
                    currency={subscription.currency}
                />
            ),
        },
        {
            id: 'actions',
            header: 'Actions',
            headerHidden: true,
            headClassName: 'w-10',
            cell: (subscription) => (
                <RowActions
                    rowLabel={subscription.vendor.shop_name}
                    groups={[
                        {
                            // An empty group renders nothing, so a lapsed period
                            // simply has no menu.
                            actions:
                                subscription.status === 'active'
                                    ? [
                                          {
                                              label: 'Cancel subscription',
                                              icon: CircleSlash,
                                              destructive: true,
                                              onSelect: () =>
                                                  setCancelling(subscription),
                                          },
                                      ]
                                    : [],
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
                { title: 'Subscriptions', href: '/admin/subscriptions' },
            ]}
        >
            <Head title="Subscriptions" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Subscriptions
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            The platform's revenue ledger.
                        </p>
                    </div>
                    <Button onClick={() => setRecording(true)}>
                        <Plus className="size-4" />
                        Record payment
                    </Button>
                </div>

                <AdminNav />

                <Alert>
                    <Banknote className="size-4" />
                    <AlertDescription>
                        Vendors pay off-platform by mobile money, bank transfer
                        or cash. Record what you have confirmed receiving — this
                        is the only money the platform earns.
                    </AlertDescription>
                </Alert>

                <Deferred
                    data="revenue"
                    fallback={
                        <div className="grid gap-4 sm:grid-cols-3">
                            {Array.from({ length: 3 }).map((_, index) => (
                                <StatCardSkeleton key={index} />
                            ))}
                        </div>
                    }
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <StatCard
                            emphasis
                            icon={Banknote}
                            label="Revenue in period"
                            value={
                                <Money
                                    amount={revenue?.period.total ?? 0}
                                    currency={revenue?.period.currency}
                                />
                            }
                            hint={`${revenue?.period.count ?? 0} payments`}
                        />
                        <StatCard
                            icon={Banknote}
                            label="Matching this filter"
                            value={
                                <Money
                                    amount={revenue?.matching_filter.total ?? 0}
                                    currency={revenue?.matching_filter.currency}
                                />
                            }
                        />
                        <StatCard
                            icon={Banknote}
                            label="All time"
                            value={
                                <Money
                                    amount={revenue?.all_time.total ?? 0}
                                    currency={revenue?.all_time.currency}
                                />
                            }
                            hint={`${revenue?.all_time.count ?? 0} payments`}
                        />
                    </div>
                </Deferred>

                <div className="flex flex-wrap gap-3">
                    <Select
                        value={values.status ?? ANY}
                        onValueChange={(value) =>
                            set('status', value === ANY ? null : value, true)
                        }
                    >
                        <SelectTrigger className="w-44" aria-label="Status">
                            <SelectValue placeholder="Any status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any status</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="expired">Expired</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select
                        value={values.period ?? ALL_TIME}
                        onValueChange={(value) =>
                            set(
                                'period',
                                value === ALL_TIME ? null : value,
                                true,
                            )
                        }
                    >
                        <SelectTrigger className="w-44" aria-label="Period">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {periodOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
                    <DataTable
                        caption="Subscription payments"
                        columns={columns}
                        rows={subscriptions.data}
                        getRowKey={(subscription) => subscription.id}
                        paginator={subscriptions}
                        className="gap-4"
                        empty={
                            <EmptyState
                                icon={Banknote}
                                title="No subscriptions"
                                description="Record a payment once a vendor has paid you."
                            />
                        }
                    />

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Expiring within 45 days
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Deferred
                                data="upcomingExpiries"
                                fallback={<ListSkeleton />}
                            >
                                {(upcomingExpiries ?? []).length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Nothing lapsing soon.
                                    </p>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {(upcomingExpiries ?? []).map(
                                            (vendor) => (
                                                <div
                                                    key={vendor.id}
                                                    className="flex items-center justify-between gap-2 py-2"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">
                                                            {vendor.shop_name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {formatRelativeDays(
                                                                vendor.subscription_ends_at,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <SubscriptionStatusBadge
                                                        status={
                                                            vendor.subscription_status
                                                        }
                                                    />
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                            </Deferred>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <RecordPaymentModal
                open={recording}
                onOpenChange={setRecording}
                vendors={vendors}
                fee={fee}
            />

            <ConfirmDialog
                open={cancelling !== null}
                onOpenChange={(open) => !open && setCancelling(null)}
                title="Cancel this subscription?"
                description="The shop stops selling immediately. The row stays in the ledger — subscriptions are never deleted, because this is the platform's revenue history."
                confirmLabel="Cancel subscription"
                onConfirm={() => {
                    if (!cancelling) {
                        return;
                    }

                    router.patch(
                        `/admin/subscriptions/${cancelling.id}/cancel`,
                        {},
                        {
                            preserveScroll: true,
                            onFinish: () => setCancelling(null),
                        },
                    );
                }}
            />
        </AppLayout>
    );
}
