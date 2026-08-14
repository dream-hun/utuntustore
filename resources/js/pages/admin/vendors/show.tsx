import { Head, Link } from '@inertiajs/react';
import { AdminNav } from '@/components/admin/admin-nav';
import type {
    AdminSubscriptionRow,
    AdminVendorDetail,
} from '@/components/admin/types';
import type { DataTableColumn } from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { Money } from '@/components/money';
import {
    OrderStatusBadge,
    SubscriptionStatusBadge,
    VendorStatusBadge,
} from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import type { OrderStatus } from '@/types/marketplace';

interface RecentOrder {
    id: string;
    order_number: string;
    parent_order_number: string;
    parent_order_id: string;
    status: OrderStatus;
    total: number;
    created_at: string | null;
}

const subscriptionColumns: DataTableColumn<AdminSubscriptionRow>[] = [
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
        id: 'recorded_by',
        header: 'Recorded by',
        cellClassName: 'text-xs',
        cell: (subscription) => subscription.recorded_by ?? '—',
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
];

const recentOrderColumns: DataTableColumn<RecentOrder>[] = [
    {
        id: 'vendor_order',
        header: 'Vendor order',
        cellClassName: 'text-sm',
        cell: (order) => order.order_number,
    },
    {
        id: 'customer_order',
        header: 'Customer order',
        cellClassName: 'text-sm',
        cell: (order) => (
            <Link
                href={`/admin/orders/${order.parent_order_id}`}
                className="hover:underline"
            >
                {order.parent_order_number}
            </Link>
        ),
    },
    {
        id: 'status',
        header: 'Status',
        cell: (order) => <OrderStatusBadge status={order.status} />,
    },
    {
        id: 'placed',
        header: 'Placed',
        cellClassName: 'text-xs text-muted-foreground',
        cell: (order) => formatDate(order.created_at),
    },
    {
        id: 'total',
        header: 'Total',
        align: 'end',
        cell: (order) => <Money amount={order.total} />,
    },
];

export default function AdminVendorShow({
    vendor,
    subscriptions,
    recentOrders,
}: {
    vendor: AdminVendorDetail;
    subscriptions: AdminSubscriptionRow[];
    recentOrders: RecentOrder[];
}) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Vendors', href: '/admin/vendors' },
                {
                    title: vendor.shop_name,
                    href: `/admin/vendors/${vendor.id}`,
                },
            ]}
        >
            <Head title={vendor.shop_name} />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {vendor.shop_name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {vendor.owner.name} · {vendor.owner.email}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <VendorStatusBadge status={vendor.status} />
                        <SubscriptionStatusBadge
                            status={vendor.subscription_status}
                        />
                        {vendor.is_platform_owned ? (
                            <Badge variant="secondary">Platform store</Badge>
                        ) : null}
                    </div>
                </div>

                <AdminNav />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">
                                Products
                            </p>
                            <p className="text-2xl font-semibold">
                                {vendor.products_count}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">
                                Orders
                            </p>
                            <p className="text-2xl font-semibold">
                                {vendor.orders_count}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">
                                Delivery areas
                            </p>
                            <p className="text-2xl font-semibold">
                                {vendor.delivery_areas_count}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent>
                            <p className="text-xs text-muted-foreground">
                                Selling now
                            </p>
                            <p className="text-2xl font-semibold">
                                {vendor.can_sell ? 'Yes' : 'No'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {vendor.subscription_ends_at
                                    ? `Until ${formatDate(vendor.subscription_ends_at)}`
                                    : 'No period'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {vendor.description || vendor.delivery_notes ? (
                    <Card>
                        <CardContent className="space-y-2 text-sm">
                            {vendor.description ? (
                                <p className="text-muted-foreground">
                                    {vendor.description}
                                </p>
                            ) : null}
                            {vendor.delivery_notes ? (
                                <p className="text-xs text-muted-foreground">
                                    Delivery notes: {vendor.delivery_notes}
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Subscription history
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            caption="Subscription history"
                            bordered={false}
                            columns={subscriptionColumns}
                            rows={subscriptions}
                            getRowKey={(subscription) => subscription.id}
                            empty={
                                <p className="text-sm text-muted-foreground">
                                    No payments recorded for this shop.
                                </p>
                            }
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Recent orders
                        </CardTitle>
                        <p className="text-xs text-muted-foreground">
                            Cash this shop collected directly from customers.
                            Not platform revenue.
                        </p>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            caption="Recent orders"
                            bordered={false}
                            columns={recentOrderColumns}
                            rows={recentOrders}
                            getRowKey={(order) => order.id}
                            empty={
                                <p className="text-sm text-muted-foreground">
                                    No orders yet.
                                </p>
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
