import { Head, Link } from '@inertiajs/react';
import { AdminNav } from '@/components/admin/admin-nav';
import type {
    AdminSubscriptionRow,
    AdminVendorDetail,
} from '@/components/admin/types';
import { Money } from '@/components/money';
import {
    OrderStatusBadge,
    SubscriptionStatusBadge,
    VendorStatusBadge,
} from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
                        {subscriptions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No payments recorded for this shop.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Period</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Reference</TableHead>
                                            <TableHead>Recorded by</TableHead>
                                            <TableHead className="text-right">
                                                Amount
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {subscriptions.map((subscription) => (
                                            <TableRow key={subscription.id}>
                                                <TableCell className="text-xs whitespace-nowrap">
                                                    {formatDate(
                                                        subscription.starts_at,
                                                    )}{' '}
                                                    –{' '}
                                                    {formatDate(
                                                        subscription.ends_at,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-xs capitalize">
                                                    {subscription.status}
                                                </TableCell>
                                                <TableCell className="text-xs">
                                                    {subscription.reference ??
                                                        '—'}
                                                </TableCell>
                                                <TableCell className="text-xs">
                                                    {subscription.recorded_by ??
                                                        '—'}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Money
                                                        amount={
                                                            subscription.amount
                                                        }
                                                        currency={
                                                            subscription.currency
                                                        }
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
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
                        {recentOrders.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No orders yet.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Vendor order</TableHead>
                                            <TableHead>
                                                Customer order
                                            </TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Placed</TableHead>
                                            <TableHead className="text-right">
                                                Total
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {recentOrders.map((order) => (
                                            <TableRow key={order.id}>
                                                <TableCell className="text-sm">
                                                    {order.order_number}
                                                </TableCell>
                                                <TableCell className="text-sm">
                                                    <Link
                                                        href={`/admin/orders/${order.parent_order_id}`}
                                                        className="hover:underline"
                                                    >
                                                        {
                                                            order.parent_order_number
                                                        }
                                                    </Link>
                                                </TableCell>
                                                <TableCell>
                                                    <OrderStatusBadge
                                                        status={order.status}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-xs text-muted-foreground">
                                                    {formatDate(
                                                        order.created_at,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Money
                                                        amount={order.total}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
