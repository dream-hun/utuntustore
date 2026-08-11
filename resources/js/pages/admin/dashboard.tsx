import { Deferred, Head, Link } from '@inertiajs/react';
import { Banknote, Store, TriangleAlert, Users } from 'lucide-react';
import { AdminNav } from '@/components/admin/admin-nav';
import {
    ListSkeleton,
    StatCard,
    StatCardSkeleton,
} from '@/components/admin/stat-card';
import type {
    ExpiringVendor,
    PlatformMetrics,
    RecentSignups,
    RevenueTotals,
} from '@/components/admin/types';
import { Money } from '@/components/money';
import {
    SubscriptionStatusBadge,
    VendorStatusBadge,
} from '@/components/status-badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatRelativeDays } from '@/lib/format';

export default function AdminDashboard({
    metrics,
    revenue,
    upcomingExpiries,
    recentSignups,
}: {
    metrics?: PlatformMetrics;
    revenue?: {
        all_time: RevenueTotals;
        this_month: RevenueTotals;
        this_year: RevenueTotals;
    };
    upcomingExpiries?: ExpiringVendor[];
    recentSignups?: RecentSignups;
}) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Admin', href: '/admin' }]}>
            <Head title="Admin dashboard" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Platform overview
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Revenue is vendor subscriptions only. Order value
                        belongs to the vendors.
                    </p>
                </div>

                <AdminNav />

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
                            label="Revenue this month"
                            value={
                                <Money
                                    amount={revenue?.this_month.total ?? 0}
                                    currency={revenue?.this_month.currency}
                                />
                            }
                            hint={`${revenue?.this_month.count ?? 0} subscription${revenue?.this_month.count === 1 ? '' : 's'}`}
                        />
                        <StatCard
                            icon={Banknote}
                            label="Revenue this year"
                            value={
                                <Money
                                    amount={revenue?.this_year.total ?? 0}
                                    currency={revenue?.this_year.currency}
                                />
                            }
                            hint={`${revenue?.this_year.count ?? 0} subscriptions`}
                        />
                        <StatCard
                            icon={Banknote}
                            label="Revenue all time"
                            value={
                                <Money
                                    amount={revenue?.all_time.total ?? 0}
                                    currency={revenue?.all_time.currency}
                                />
                            }
                            hint={`${revenue?.all_time.count ?? 0} subscriptions`}
                        />
                    </div>
                </Deferred>

                <Deferred
                    data="metrics"
                    fallback={
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {Array.from({ length: 4 }).map((_, index) => (
                                <StatCardSkeleton key={index} />
                            ))}
                        </div>
                    }
                >
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard
                            icon={Store}
                            label="Vendors"
                            value={metrics?.vendors.total ?? 0}
                            hint={`${metrics?.vendors.pending ?? 0} awaiting review`}
                        />
                        <StatCard
                            icon={Store}
                            label="Currently selling"
                            value={metrics?.selling.active ?? 0}
                            hint={`${metrics?.selling.grace ?? 0} in grace · ${metrics?.selling.expired ?? 0} expired`}
                        />
                        <StatCard
                            icon={Banknote}
                            label="Active subscriptions"
                            value={metrics?.active_subscriptions ?? 0}
                        />
                        <StatCard
                            icon={Users}
                            label="Customers"
                            value={metrics?.customers ?? 0}
                        />
                    </div>
                </Deferred>

                {(metrics?.vendors.pending ?? 0) > 0 ? (
                    <Alert>
                        <TriangleAlert className="size-4" />
                        <AlertDescription>
                            {metrics?.vendors.pending} vendor application
                            {metrics?.vendors.pending === 1 ? '' : 's'} waiting
                            for review.{' '}
                            <Link
                                href="/admin/vendors?status=pending"
                                className="font-medium underline"
                            >
                                Review them
                            </Link>
                            .
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Expiring within 30 days
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Deferred
                                data="upcomingExpiries"
                                fallback={<ListSkeleton />}
                            >
                                {(upcomingExpiries ?? []).length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No subscriptions lapsing soon.
                                    </p>
                                ) : (
                                    <div className="divide-y divide-border">
                                        {(upcomingExpiries ?? []).map(
                                            (vendor) => (
                                                <Link
                                                    key={vendor.id}
                                                    href={`/admin/vendors/${vendor.id}`}
                                                    className="-mx-2 flex items-center justify-between gap-3 rounded-md px-2 py-2 transition-colors hover:bg-accent/50"
                                                >
                                                    <div className="min-w-0">
                                                        <p className="truncate text-sm font-medium">
                                                            {vendor.shop_name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {vendor.phone}
                                                        </p>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatRelativeDays(
                                                                vendor.subscription_ends_at,
                                                            )}
                                                        </span>
                                                        <SubscriptionStatusBadge
                                                            status={
                                                                vendor.subscription_status
                                                            }
                                                        />
                                                    </div>
                                                </Link>
                                            ),
                                        )}
                                    </div>
                                )}
                            </Deferred>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Recent signups
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Deferred
                                data="recentSignups"
                                fallback={<ListSkeleton />}
                            >
                                <div className="space-y-4">
                                    <div>
                                        <p className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                                            Vendors
                                        </p>
                                        {(recentSignups?.vendors ?? [])
                                            .length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                None yet.
                                            </p>
                                        ) : (
                                            <div className="divide-y divide-border">
                                                {(
                                                    recentSignups?.vendors ?? []
                                                ).map((vendor) => (
                                                    <div
                                                        key={vendor.id}
                                                        className="flex items-center justify-between gap-3 py-2 text-sm"
                                                    >
                                                        <span className="min-w-0 truncate">
                                                            {vendor.shop_name}
                                                        </span>
                                                        <VendorStatusBadge
                                                            status={
                                                                vendor.status
                                                            }
                                                        />
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>

                                    <div>
                                        <p className="mb-1 text-xs font-medium text-muted-foreground uppercase">
                                            Customers
                                        </p>
                                        {(recentSignups?.customers ?? [])
                                            .length === 0 ? (
                                            <p className="text-sm text-muted-foreground">
                                                None yet.
                                            </p>
                                        ) : (
                                            <div className="divide-y divide-border">
                                                {(
                                                    recentSignups?.customers ??
                                                    []
                                                ).map((customer) => (
                                                    <div
                                                        key={customer.id}
                                                        className="flex items-center justify-between gap-3 py-2 text-sm"
                                                    >
                                                        <span className="min-w-0 truncate">
                                                            {customer.name}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatDate(
                                                                customer.created_at,
                                                            )}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </Deferred>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
